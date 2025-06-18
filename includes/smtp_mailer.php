<?php
/**
 * Simple SMTP Mailer for Gmail
 */

class SMTPMailer {
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 587;
    private $username = 'helloborislav@gmail.com';
    private $password = 'kingsm22';
    private $socket;
    
    public function sendMail($to, $subject, $message, $from_name = 'Barbershop Management System') {
        try {
            if ($this->connect() && $this->authenticate() && $this->sendEmail($to, $subject, $message, $from_name)) {
                $this->disconnect();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function connect() {
        $errno = $errstr = null;
        $this->socket = fsockopen($this->smtp_host, $this->smtp_port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }
        
        $this->getResponse(); // Initial greeting
        
        // Send EHLO
        $this->sendCommand("EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
        
        // Start TLS
        $this->sendCommand("STARTTLS");
        
        // Enable encryption
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // Send EHLO again after TLS
        $this->sendCommand("EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
        
        return true;
    }
    
    private function authenticate() {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
        return true;
    }
    
    private function sendEmail($to, $subject, $message, $from_name) {
        // Set sender
        $this->sendCommand("MAIL FROM: <{$this->username}>");
        
        // Set recipient
        $this->sendCommand("RCPT TO: <$to>");
        
        // Start data transmission
        $this->sendCommand("DATA");
        
        // Send headers and message
        $email_content = $this->buildEmailContent($to, $subject, $message, $from_name);
        fputs($this->socket, $email_content . "\r\n.\r\n");
        $this->getResponse();
        
        return true;
    }
    
    private function buildEmailContent($to, $subject, $message, $from_name) {
        $boundary = uniqid('boundary_');
        
        $content = "From: $from_name <{$this->username}>\r\n";
        $content .= "To: $to\r\n";
        $content .= "Subject: $subject\r\n";
        $content .= "MIME-Version: 1.0\r\n";
        $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $content .= "Content-Transfer-Encoding: 8bit\r\n";
        $content .= "Date: " . date('r') . "\r\n";
        $content .= "\r\n";
        $content .= $message;
        
        return $content;
    }
    
    private function sendCommand($command) {
        fputs($this->socket, $command . "\r\n");
        return $this->getResponse();
    }
    
    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
    
    private function disconnect() {
        if ($this->socket) {
            $this->sendCommand("QUIT");
            fclose($this->socket);
        }
    }
}

/**
 * Send backup notification using SMTP
 */
function sendBackupEmailSMTP($to, $subject, $message) {
    $mailer = new SMTPMailer();
    return $mailer->sendMail($to, $subject, $message);
}
?>