<?php namespace RestExtension\Exceptions;

class UnauthorizedException extends \Exception {

    protected $code = 401;
    protected $message = 'Unauthorized';

}
