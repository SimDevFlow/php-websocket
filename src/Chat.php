<?php
namespace MyApp;

// use Ratchet\ConnectionInterface;
// use Ratchet\MessageComponentInterface;

// class Chat implements MessageComponentInterface
// {
//     protected $clients;

//     public function __construct() {
//         $this->clients = new \SplObjectStorage;
//     }

//     public function onOpen(ConnectionInterface $conn) {
//         $this->clients->attach($conn);

//         echo "New connection! ({$conn->resourceId})\n";
//     }

//     public function onMessage(ConnectionInterface $from, $msg) {
//         $numRecv = count($this->clients) - 1;
//         echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
//             , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

//         foreach ($this->clients as $client) {
//             if ($from !== $client) {
//                 $client->send($msg);
//             }
//         }
//     }

//     public function onClose(ConnectionInterface $conn) {
//         $this->clients->detach($conn);

//         echo "Connection {$conn->resourceId} has disconnected\n";
//     }

//     public function onError(ConnectionInterface $conn, \Exception $e) {
//         echo "An error has occurred: {$e->getMessage()}\n";

//         $conn->close();
//     }
// }

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $channels; // Stocke les connexions par channel

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->channels = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $queryParams);

        $channel = $queryParams['channel'] ?? 'default'; // Récupérer le channel
        $user = $queryParams['user'] ?? 'default';
        $this->clients->attach($conn, ['channel' => $channel]);

        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = new \SplObjectStorage;
        }
        $this->channels[$channel]->attach($conn);

        echo "Nouvelle connexion sur le channel: $channel par $user\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $channel = $this->clients[$from]['channel'];

        if (!isset($this->channels[$channel])) {
            return;
        }

        // Diffuser le message uniquement aux connexions dans le même channel
        foreach ($this->channels[$channel] as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $channel = $this->clients[$conn]['channel'];
        
        if (isset($this->channels[$channel])) {
            $this->channels[$channel]->detach($conn);
            if ($this->channels[$channel]->count() === 0) {
                unset($this->channels[$channel]); // Supprimer le channel s'il est vide
            }
        }
        
        $this->clients->detach($conn);
        echo "Déconnexion du channel: $channel\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Erreur : {$e->getMessage()}\n";
        $conn->close();
    }
}
