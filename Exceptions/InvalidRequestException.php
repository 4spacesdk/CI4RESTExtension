<?php namespace RestExtension\Exceptions;

class InvalidRequestException extends \Exception {

    protected $code = 422;
    protected $message = 'Invalid Request';

}
