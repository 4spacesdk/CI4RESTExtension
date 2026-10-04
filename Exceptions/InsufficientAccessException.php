<?php namespace RestExtension\Exceptions;

/**
 * A rule said no to a write. With writesFollowRules on, the resource controller answers it with
 * 403 instead of the unsaved row.
 */
class InsufficientAccessException extends RestException {

    public function __construct(string $message = 'InsufficientAccess') {
        parent::__construct($message, 403);
    }

}
