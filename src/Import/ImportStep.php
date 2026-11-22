<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Import;

/**
 * Next step of import, which should be shown to user.
 *
 * @internal used by ImportControllerTrait, it is not covered by backward compatibility promise yet
 */
enum ImportStep
{
    /** Form with file has to be shown, e.g. file was not sent yet or it is invalid. */
    case SELECT_FILE;
    /** Preview of data from file has to be shown, so user can check and edit it before saving. */
    case EDIT_MATRIX;
    /** Import is finished, successfully or not (see messages), user should be redirected to beginning of import. */
    case FINISHED;
}
