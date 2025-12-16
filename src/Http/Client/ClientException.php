<?php

declare(strict_types=1);

namespace Verge\Http\Client;

use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \RuntimeException implements ClientExceptionInterface
{
}
