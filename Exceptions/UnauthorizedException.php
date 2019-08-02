<?php namespace RestExtension\Exceptions;

class UnauthorizedException extends RestException {

    protected $code = 401;
    protected $message = 'Unauthorized';

}
