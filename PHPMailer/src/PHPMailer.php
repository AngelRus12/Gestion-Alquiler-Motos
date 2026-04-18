<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const ENCRYPTION_SMTPS = 'ssl';
    const ENCRYPTION_STARTTLS = 'tls';

    public $Priority = null;
    public $CharSet = 'iso-8859-1';
    public $ContentType = 'text/plain';
    public $Encoding = '8bit';
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $WordWrap = 0;
    public $Mailer = 'smtp';
    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPAuth = false;
    public $SMTPSecure = '';
    public $Username = '';
    public $Password = '';
    public $Timeout = 20;
    protected $to = [];
    public $ErrorInfo = '';

    public function isSMTP() { $this->Mailer = 'smtp'; }
    public function isHTML($ishtml = true) { $this->ContentType = $ishtml ? 'text/html' : 'text/plain'; }

    public function setFrom($address, $name = '') {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress($address, $name = '') {
        $this->to[] = [$address, $name];
    }

    public function send() {
        try {
            $smtp = new SMTP();
            if (!$smtp->connect($this->Host, $this->Port, $this->Timeout)) throw new Exception('Connect failed');
            $smtp->hello();
            if ($this->SMTPAuth && !$smtp->authenticate($this->Username, $this->Password)) throw new Exception('Auth failed');
            $smtp->mail($this->From);
            foreach ($this->to as $to) $smtp->recipient($to[0]);
            
            $header = "Date: " . date('D, j M Y H:i:s O') . "\r\n";
            $header .= "To: " . $this->to[0][0] . "\r\n";
            $header .= "From: " . $this->FromName . " <" . $this->From . ">\r\n";
            $header .= "Subject: " . $this->Subject . "\r\n";
            $header .= "Content-Type: " . $this->ContentType . "; charset=" . $this->CharSet . "\r\n\r\n";
            
            if (!$smtp->data($header . $this->Body)) throw new Exception('Data failed');
            $smtp->quit();
            return true;
        } catch (Exception $e) {
            $this->ErrorInfo = $e->errorMessage();
            return false;
        }
    }
}