<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Exception;

/**
 * General exception thrown when matrix record has invalid data.
 */
class MatrixException extends Exception implements BatchEntityImportExceptionInterface
{
}
