<?php
declare(strict_types=1);

namespace App;

/** Thrown when the MySQL server can't be reached or set up. */
class DatabaseUnavailable extends \RuntimeException
{
}
