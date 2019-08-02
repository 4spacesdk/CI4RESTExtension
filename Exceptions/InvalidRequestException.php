<?php namespace RestExtension\Exceptions;

class InvalidRequestException extends RestException {

    protected $code = 422;
    protected $message = 'Invalid Request';

}
