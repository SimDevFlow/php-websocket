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

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $channels; // Stocke les connexions par channel
    private $pdo;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->channels = [];

        $dsn = 'mysql:host=localhost;dbname=chat_api';
        $username = 'root';
        $password = '';

        try {
            $this->pdo = new \PDO($dsn, $username, $password);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            echo "Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
            exit;
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $queryParams);

        $channel = $queryParams['channel'] ?? 'default'; // Récupérer le channel
        $user_id = $queryParams['user_id'] ?? null;
        $this->clients->attach($conn, ['channel' => $channel]);

        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = new \SplObjectStorage;
        }
        $this->channels[$channel]->attach($conn);

        echo "Nouvelle connexion sur le channel: $channel\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
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
        
        if ($data && isset($data['message'], $data['sender_id'], $data['receiver_id'])) {
            $message = $data['message'];
            $senderId = $data['sender_id'];
            $receiverId = $data['receiver_id'];
    
            // Traiter le message en fonction des besoins
            // Par exemple, envoyer le message au destinataire spécifique
            $this->saveMessageToDatabase($senderId, $receiverId, $channel, $message);
        } else {
            echo "Message JSON invalide reçu : $msg\n";
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
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

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Erreur : {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function saveMessageToDatabase($sender_id, $receiver_id, $channel, $message) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, channel, messagex) VALUES (:sender_id, :receiver_id, :channel, :messagex)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sender_id' => $sender_id,
            ':receiver_id' => $receiver_id,
            ':channel' => $channel,
            ':messagex' => $message
        ]);
    }
}
