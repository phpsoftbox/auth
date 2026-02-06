<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use InvalidArgumentException;
use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;

use function is_array;
use function sprintf;

final class FileUserProvider extends ArrayUserProvider
{
    /**
     * @param list<string> $loginFields
     * @param list<CredentialsValidatorInterface>|null $validators
     */
    public function __construct(
        string $path,
        array $loginFields = ['email'],
        string $idField = 'id',
        ?array $validators = null,
    ) {
        $users = require $path;
        if (!is_array($users)) {
            throw new InvalidArgumentException(sprintf('User file "%s" must return array.', $path));
        }

        parent::__construct($users, $loginFields, $idField, $validators);
    }
}
