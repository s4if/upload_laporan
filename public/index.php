<?php
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Http\Stream;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Instantiate App
$app = AppFactory::create();

// Set session
$app->add(
  new \Slim\Middleware\Session([
    'name' => 'laporan_daring',
    'autorefresh' => true,
    'lifetime' => '1 hour',
  ])
);

// Set view in Container
$container->set('view', function() {
    return Twig::create('../views', [
    	//pas deploy ini di uncomment
    	//'cache' => '../cache/twig',
    ]);
});

$container->set('baseUrl', function(){
	return 'http://localhost:3000/';
});

$container->set('dbConf', function() {
	return [
		'dsn' => 'sqlite:../db.sqlite',
		'username' => '',
		'password' => ''
	]; 
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('session', function () {
  return new \SlimSession\Helper();
});

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));


// Add error middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Login Middleware
$simpleAuth = function (Request $req, RequestHandler $handler) {
    $res = $handler->handle($req);
    $session = new \SlimSession\Helper();
    if (empty($session->username)) {
    	$flash = new \Slim\Flash\Messages();
    	$flash->addMessage('error',"Maaf, anda harus login dulu.");
		return $res->withHeader('Location',$this->get('baseUrl').'login')
			->withStatus(303); 
    } else {
    	return $res;
    }
};

$redirHome = function (Request $req, RequestHandler $handler) {
	$res = $handler->handle($req);
    $session = new \SlimSession\Helper();
    if (!empty($session->username)) {
    	$flash = new \Slim\Flash\Messages();
    	$flash->addMessage('error',"Maaf, anda sudah login.");
		return $res->withHeader('Location',$this->get('baseUrl').'home')
			->withStatus(303); 
    } else {
    	return $res;
    }
};

// Add routes
$app->redirect('/', '/login', 301);

$app->get('/login[/{folder}]', function (Request $req, Response $res, $args){
	return $this->get('view')->render($res, 'login.html', [
		'baseUrl' => $this->get('baseUrl'),
		'error_msg' => $this->get('flash')->getFirstMessage('error'),
		'folder' => empty($args['folder'])?"":'/'.$args['folder']
	]);
})->add($redirHome);

$app->post('/login[/{folder}]', function(Request $req, Response $res, $args){
	$params = (array)$req->getParsedBody();
	$dbConf = $this->get('dbConf');
	$pdo = new \FaaPz\PDO\Database($dbConf['dsn'], $dbConf['username'], $dbConf['password']);
	$sql = $pdo->select()
				->from('users')
				->where(new FaaPz\PDO\Clause\Conditional('username',"=",$params['username']));
	$stmt = $sql->execute();
	$data = $stmt->fetch();
	if (!empty($data)) {
		if (password_verify($params['password'], $data['password'])) {
			$this->get('session')->set('username', $data['username']);
			$this->get('session')->set('nama', $data['nama']);
			$this->get('session')->set('kelas', $data['kelas']);
			$this->get('flash')->addMessage('sukses',"Selamat, anda berhasil login!");
			$str_folder = empty($args['folder'])?"":"/".$args['folder'];
			return $res->withHeader('Location',$this->get('baseUrl').'home'.$str_folder)
				->withStatus(303);
		} else {
			$this->get('flash')->addMessage('error',"Maaf, password salah");
			$str_folder = empty($args['folder'])?"":"/".$args['folder'];
			return $res->withHeader('Location',$this->get('baseUrl').'login'.$str_folder)
				->withStatus(303);
		}
	} else {
		$this->get('flash')->addMessage('error',"Maaf, username salah");
		$str_folder = empty($args['folder'])?"":"/".$args['folder'];
		return $res->withHeader('Location',$this->get('baseUrl').'login'.$str_folder)
			->withStatus(303);
	}
});

$app->get('/home[/{folder}]', function(Request $req, Response $res, $args){
	$arrDir = array_diff(scandir('../data/', SCANDIR_SORT_NONE), array('..', '.'));
	$arrLaporan = [];
	foreach ($arrDir as $direktori) {
		$alias = file_get_contents('../data/'.$direktori.'/alias.txt');
		$arrLaporan[] = [
			'direktori' => $direktori, 
			'alias' => $alias
		];
	}
	$title = "Home";
	if (file_exists('../data/'.$args['folder'].'/alias.txt')) {
		$title = file_get_contents('../data/'.$args['folder'].'/alias.txt');
	}
	$sukses_msg = $this->get('flash')->getFirstMessage('sukses');
	return $this->get('view')->render($res, 'home.html', [
		'baseUrl' => $this->get('baseUrl'),
		'error_msg' => $this->get('flash')->getFirstMessage('error'),
		'nama' => $this->get('session')->nama,
		'kelas' => $this->get('session')->kelas,
		'username' => $this->get('session')->username,
		'folder' => $args['folder'],
		'arrLaporan' => $arrLaporan,
		'title' => $title
	]);
})->add($simpleAuth);

$app->get('/hash/{pass}', function(Request $req, Response $res, $args){
	$res->getBody()->write(password_hash($args['pass'], PASSWORD_BCRYPT));
	return $res;
});

$app->get('/logout', function(Request $req, Response $res){
	$this->get('session')::destroy();
	return $res->withHeader('Location',$this->get('baseUrl').'login')
			->withStatus(303);
});

$app->get('/pdf/{folder}/{kelas}/{username}/{download}', function(Request $req, Response $res, $args){
	$userPath = $args['folder'].'/'.$args['kelas'].'/'.$args['username'];
    $path = '../data/'.$userPath.'.pdf';
    if (!file_exists($path)){
    	$path = '../default.pdf';
    }
    $disposition = ($args['download'] == 'download')?'attachment':'inline';
    $file_stream = new \GuzzleHttp\Psr7\LazyOpenStream($path, 'r');
    return $res->withBody($file_stream)
        ->withHeader('Content-Disposition', $disposition.'; filename='.$args['username'].'.pdf;')
        ->withHeader('Content-Type', mime_content_type($path))
        ->withHeader('Content-Length', filesize($path));
});

$app->run();