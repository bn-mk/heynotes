<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

class MongoSessionHandler extends DatabaseSessionHandler
{
    /**
     * {@inheritdoc}
     */
    public function read($sessionId): false|string
    {
        $session = (object) $this->getQuery()->where('id', $sessionId)->first();

        if ($this->expired($session)) {
            $this->exists = true;

            return '';
        }

        if (isset($session->payload)) {
            $this->exists = true;

            return base64_decode($session->payload);
        }

        return '';
    }
}
