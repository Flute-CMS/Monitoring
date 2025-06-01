<?php

namespace Flute\Modules\Monitoring\Services\ProtocolHandlers;

use Flute\Core\Database\Entities\Server;
use Flute\Modules\Monitoring\database\Entities\ServerStatus;

interface ProtocolHandlerInterface
{
    public function updateStatus(Server $server, ServerStatus $status): void;
} 