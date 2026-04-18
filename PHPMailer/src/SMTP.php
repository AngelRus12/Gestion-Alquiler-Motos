<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = '6.9.1';
    const LE = "\r\n";
    protected $smtp_conn;
    protected $error = [];

    public function connect($host, $port = 465, $timeout = 30) {
        // x10hosting suele requerir ssl:// para el puerto 465
        $protocol = ($port == 465) ? 'ssl://' : '';
        $this->smtp_conn = @fsockopen($protocol . $host, $port, $errno, $errstr, $timeout);
        if (!$this->smtp_conn) return false;
        $this->getResponse();
        return true;
    }

    public function hello($host = '') {
        return $this->sendCommand('EHLO ' . ($host ?: 'localhost'), 250);
    }

    public function authenticate($user, $pass) {
        if (!$this->sendCommand('AUTH LOGIN', 334)) return false;
        if (!$this->sendCommand(base64_encode($user), 334)) return false;
        if (!$this->sendCommand(base64_encode($pass), 235)) return false;
        return true;
    }

    public function mail($from) {
        return $this->sendCommand('MAIL FROM:<' . $from . '>', 250);
    }

    public function recipient($to) {
        return $this->sendCommand('RCPT TO:<' . $to . '>', 250);
    }

    public function data($msg_data) {
        if (!$this->sendCommand('DATA', 354)) return false;
        fputs($this->smtp_conn, $msg_data . self::LE . '.' . self::LE);
        return $this->getResponse(250);
    }

    public function quit() {
        $this->sendCommand('QUIT', 221);
        fclose($this->smtp_conn);
    }

    protected function sendCommand($command, $expect) {
        fputs($this->smtp_conn, $command . self::LE);
        return $this->getResponse($expect);
    }

    protected function getResponse($expect = null) {
        $res = '';
        while ($str = fgets($this->smtp_conn, 515)) {
            $res .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        if ($expect !== null && substr($res, 0, 3) != $expect) return false;
        return $res;
    }
}