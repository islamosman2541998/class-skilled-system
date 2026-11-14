<?php

namespace App\Exceptions;

use RuntimeException;

/** A file whose real type, extension or size is not allowed. The message is already translated. */
class InvalidUploadException extends RuntimeException {}
