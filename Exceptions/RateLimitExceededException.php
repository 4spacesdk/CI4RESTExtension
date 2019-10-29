<?php namespace RestExtension\Exceptions;

class RateLimitExceededException extends RestException {

    protected $code = 429;
    protected $message = 'RateLimitExceeded';

}
