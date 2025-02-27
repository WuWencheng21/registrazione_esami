<?php

use Model\VotoRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use DI\Container as Container;
use League\Plates\Engine as Engine;
use Tuupola\Middleware\JwtAuthentication;
use Util\Connection;


require __DIR__ . '/../vendor/autoload.php';
require_once '../conf/config.php';

$container = new Container();
//da inserire prima della create di AppFactory
AppFactory::setContainer($container);

$container->set('template', function (){
    $engine = new Engine('../templates', 'phtml');
    $engine->addData([ 'basepath' => BASE_PATH]);
    return $engine;
});

$container->set('connection', function (){
    return Connection::getInstance();
});

$app = AppFactory::create();

// Per usare questa classe bisogno installarla con Composer
// composer require tuupola/slim-jwt-auth
$app->add(new JwtAuthentication([
    "path" => [BASE_PATH],
    "ignore" => [BASE_PATH . "/login", BASE_PATH . "/autenticazione",
        BASE_PATH . "/images"],
    //Questa parte fa in modo che se l'autenticazione fallisce per qualsiasi
    // motivo venga fatto un
    //redirect verso la pagina di login
    "error" => function ($response, $arguments) {
        return $response
            ->withHeader("Location", BASE_PATH . '/login')
            ->withStatus(301);
    },
    "secret" => JWT_SECRET
]));

/**
 * Add Error Middleware
 *
 * @param bool                  $displayErrorDetails -> Should be set to false in production
 * @param bool                  $logErrors -> Parameter is passed to the default ErrorHandler
 * @param bool                  $logErrorDetails -> Display error details in error log
 * @param LoggerInterface|null  $logger -> Optional PSR-3 Logger
 *
 * Note: This middleware should be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

//Questa parte deve essere sostituita con il nome della propria+
//sottocartella dove si trova l'applicazione
$app->setBasePath(BASE_PATH);

$app->get('/', function (Request $request, Response $response) {
    return $response->withStatus(302)->withHeader('Location', BASE_PATH . '/login');
});

/*
 * Rotta che mostra il form di login
 */
$app->get('/login', function (Request $request, Response $response) {
    //Se il login è già stato effettuato ridirigo verso la ricerca dello studente
    if (isset($_COOKIE['token']))
        return $response->withStatus(302)->withHeader('Location', BASE_PATH . '/studente/cerca');
    $template = $this->get('template');
    $response->getBody()->write($template->render('login'));
    return $response;
});

/*
 * Rotta che genera il token JWT dopo aver proceduto all'autenticazione
 */
$app->post('/autenticazione', function (Request $request, Response $response) {
    //Se il login è già stato effettuato ridirigo verso la ricerca dello studente
    if (isset($_COOKIE['token']))
        return $response->withStatus(302)->withHeader('Location', BASE_PATH . '/studente/cerca');
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];
    $jwt = \Model\ProfessoreRepository::verificaAutenticazione($username,$password);
    if ($jwt !== null) {
        setcookie('token', $jwt);
        return $response->withStatus(302)->withHeader('Location', BASE_PATH . '/studente/cerca');
    }
    else{
        $template = $this->get('template');
        $response->getBody()->write($template->render('login',[
            'login_fallito' => true
        ]));
    }
    return $response;
});



/*
 * Rotta per la creazione della form di ricerca di uno studente
 * tramite la matricola
 */
$app->get('/studente/cerca', function (Request $request, Response $response) {
    $template = $this->get('template');
    $response->getBody()->write($template->render('cerca_studente'));
    return $response;
}
);

$app->get('/studente/inserisci', function (Request $request, Response $response) {
    $template = $this->get('template');
    $response->getBody()->write($template->render('inserisci_studente'));
    return $response;
}
);



$app->get('/studente/elenco', function (Request $request, Response $response) {
    $dsn = 'mysql:host=' . HOSTNAME . ';dbname=' . DBNAME . ';charset=utf8';
    try {
        $pdo = new PDO($dsn, USERNAME, PASSWORD);
    } catch (Exception $e) {
        echo $e->getMessage();
        exit(1);
    }
    $stmt = $pdo->query('SELECT id, cognome, nome, matricola, voto, id_corso FROM studente GROUP BY nome');

    $result = $stmt->fetchAll();

    $template = $this->get('template');
    $response->getBody()->write($template->render('studente_elenco',
        [
            'studente' => $result
        ]
    ));
    return $response;
}
);

/*
 * Gestisce il caso che sia presente o meno lo studente
 * - se è già presente mostra la form di inserimento del voto
 * - altrimenti ridirigere verso la pagina di aggiunta dello studente
 */
$app->post('/voto/form', function (Request $request, Response $response) {
    //Serve a fare il parsing dei dati contenuti nel body
    //spediti tramite la form con il metodo POST
    $data = $request->getParsedBody();
    //Recupero la matricola
    $matricola = $data['matricola'];
    //Controllo se è presente, nella realtà verificando
    //all'interno del database, qua è solo per prova
    if(\Model\StudenteRepository::controlloStudente($matricola))
        {
            return $response->withStatus(302)->withHeader('Location', BASE_PATH . '/studente/form');
        }
    $infoStudente = \Model\StudenteRepository::getIdFromMatricola($matricola);
    if ($matricola === $infoStudente['matricola']) {
        $template = $this->get('template');
        $response->getBody()->write($template->render('inserisci_voto', [
            'matricola' => $matricola,
            'infoStudente' => $infoStudente
        ]));
        return $response;
    }
    else{
        return $response->withStatus(302)->withHeader('Location', BASE_PATH . '/studente/form');
    }
}
);

/*
 * Inserisce il voto dello studente individuato dalla {matricola},
 * da completare con l'informazione sul professore che inserisce il voto
 * e dell'esame al quale si riferisce
 */

$app->post('/studente/{matricola}/voto', function (Request $request, Response $response, $args) {
    //Qui andrebbe il codice associato all'inserimento del voto nel database
    $data = $request->getParsedBody();
    $matricola_studente = $args['matricola'];
    $voto = $data['voto'];
    $esito = $data['esito'];
    $tipo_esame = $data['tipoEsame'];
    $dataEsame = $data['dataEsame'];
    $id_professore = $data['id_professore'];
    $successo = VotoRepository::inserisciVoto($voto, $matricola_studente,
        $esito, $tipo_esame, $dataEsame, $id_professore);
    if ($successo){
        $template = $this->get('template');
        $response->getBody()->write($template->render('studente_voto'));
}
    else
        $response->getBody()->write('Voto non inserito');
    return $response;
});

/*
 * Genera il form per l'inserimento di uno studente
 */
$app->get('/studente/form', function (Request $request, Response $response) {
    $template = $this->get('template');
    $response->getBody()->write($template->render('inserisci_studente'));
    return $response;
});

$app->post('/studente/inserisci', function (Request $request, Response $response, $args) {
    //Qui andrebbe il codice associato all'inserimento del voto nel database
    $data = $request->getParsedBody();
    $matricola = $data['matricola'];
    $nome = $data['nome'];
    $cognome = $data['cognome'];
    $id_corso = $data['corso'];
    $successo = \Model\StudenteRepository::inserisciStudente($matricola, $nome, $cognome, $id_corso);
    if ($successo)
        $response->getBody()->write('Studente inserito');
    else
        $response->getBody()->write('Studente non inserito');
    return $response;
});


$app->post('/visualizzaVoto', function (Request $request, Response $response){
    $template = $this->get('template');
    $response->getBody()->write($template->render('studente_voto'));
    return $response;
});

$app->post('/voto/elenco', function (Request $request, Response $response){
    $data = $request->getParsedBody();
    $matricola = $data['matricola'];
    $elencoVoto = \Model\VotoRepository::visualizzaElencoVoto($matricola);
    $template = $this->get('template');
    $response->getBody()->write($template->render('visualizza_voto', [
        'elencoVoto' => $elencoVoto
    ]));
    return $response;

});


//Rotta per gli assett (immagini, file css, ecc.,
// deve essere messa in fondo a tutte le rotte
//altrimenti le intercetta
$app->get('/{folder}/{file}', function (Request $request, Response $response, $args) {
    //Crea il percorso fisico dove si dovrebbe trovare il file
    //Ad esempio D:\xampp_7_4_25\htdocs\registrazione_esami\public/../images/logo.jpg
    $filePath = __DIR__ . '/../' . $args['folder']. '/'. $args['file'];
    //Controllo dell'esistenza del file
    if (!file_exists($filePath)) {
        return $response->withStatus(404, 'File Not Found');
    }
    //Si controlla l'estensione del file
    switch (pathinfo($filePath, PATHINFO_EXTENSION)) {
        case 'css':
            $mimeType = 'text/css';
            break;

        case 'jpg':
            $mimeType = 'application/jpeg';
            break;

        // Per evitare di rimandare indietro file con estensione
        // diversa da quelle riconosciute, nel default si fa in
        // modo di non mandare indietro nulla
        default:
            $mimeType = 'text/html';
            $filePath = null;
    }
    //Aggiunge nell'header il content type corretto, che ho individuato
    //con lo switch di prima
    $response = $response->withHeader('Content-Type',$mimeType);
    // Scrive nel body il contenuto del file, se è tra quelli con estensioni
    // conosciute
    if ($filePath !== null)
        $response->getBody()->write(file_get_contents($filePath));
    return $response;
});

$app->run();