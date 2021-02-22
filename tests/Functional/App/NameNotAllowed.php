<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Lcobucci\ErrorHandling\Problem\Titled;
use Lcobucci\ErrorHandling\Problem\UnprocessableRequest;
use RuntimeException;

final class NameNotAllowed extends RuntimeException implements UnprocessableRequest, Titled
{
    public function getTitle(): string
    {
        return 'Name not allowed';
    }
}
