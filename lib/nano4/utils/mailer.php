<?php

/**
 * A quick class to send e-mails with.
 * Use it as a standalone component, or extend it for additional features.
 * It now uses Swift Mailer as its backend for added flexibility.
 * Swiftmailer must be installed via Pear.
 */

namespace Nano4\Utils;

require_once 'swift_required.php';

class Mailer
{
  // Internal rules.
  protected $fields;     // Field rules. 'true' required, 'false' optional.
  protected $template;   // Default template to use for e-mails.
  protected $views;      // Nano loader to use to load template.
  protected $mailer;     // The Swift Mailer object.

  // Public fields. Reset on each send().
  public $failures;     // A list of messages that failed.
  public $missing;      // Set to an array if a required field wasn't set.
  public $message;      // The Swift Message object.

  // Set to true to enable logging errors.
  public $log_errors = False;
  public $log_message = False;

  public function __construct ($fields, $opts=array())
  {
    if (!is_array($fields))
      throw new \Nano4\Exception('NanoMailer requires a field list.');
    $this->fields = $fields;
    if (isset($opts['template']))
      $this->template = $opts['template'];

    if (isset($opts['views']))
      $this->views = $opts['views'];
    elseif (!isset($this->views))
      $this->views = 'views'; // Default if nothing else is set.

    if (isset($opts['transport']))
      $transport = $opts['transport'];
    elseif (isset($opts['host']))
    { // Using SMTP transport.
      $transport = \Swift_SmtpTransport::newInstance($opts['host']);
      if (isset($opts['port']))
        $transport->setPort($opts['port']);
      if (isset($opts['enc']))
        $transport->setEncryption($opts['enc']);
      if (isset($opts['user']))
        $transport->setUsername($opts['user']);
      if (isset($opts['pass']))
        $transport->setPassword($opts['pass']);
    }
    else
    { // Using sendmail transport.
      $transport = \Swift_SendmailTransport::newInstance();
    }

    $this->mailer = \Swift_Mailer::newInstance($transport);

    $this->message = \Swift_Message::newInstance();

    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    if (isset($opts['from']))
      $this->message->setFrom($opts['from']);

    if (isset($opts['to']))
      $this->message->setTo($opts['to']);

  }

  public function send ($data, $opts=array())
  {
    // First, let's reset our special attributes.
    $this->missing  = array();
    $this->failures = array();

    // Find the subject.
    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    // Find the recipient.
    if (isset($opts['to']))
      $this->message->setTo($opts['to']);

    // Find the template to use.
    if (isset($opts['template']))
      $template = $opts['template'];
    elseif (isset($this->template))
      $template = $this->template;
    else
      $template = Null; // We're not using a template.

    // Populate the fields for the e-mail message.
    $fields = array();
    foreach ($this->fields as $field=>$required)
    {
      if (isset($data[$field]) && $data[$field] != '')
        $fields[$field] = $data[$field];
      elseif ($required)
        $this->missing[$field] = true;
    }

    // We can only continue if all required fields are present.
    if (count($this->missing))
    { // We have missing values.
      if ($this->log_errors)
      {
        error_log("Message data: ".json_encode($message));
        error_log("Mailer missing: ".json_encode($this->missing));
      }
      return false;
    }

    // Are we using templates or not?
    // Templates are highly recommended.
    if (isset($template))
    { // We're using templates (recommended.)
      $nano = \Nano4\get_instance();
      $loader = $this->views;
      #error_log("template: '$template', loader: '$loader'");
      if (isset($nano->lib[$loader]))
      { // We're using a view loader.
        $message = $nano->lib[$loader]->load($template, $fields);
      }
      else
      { // View library wasn't found. Assuming a full PHP include file path.
        $message = \Nano4\get_php_content($template, $fields);
      }
    }
    else
    { // We're not using a template. Build the message manually.
      $message = "---\n";
      foreach ($fields as $field=>$value)
      {
        $message .= " $field: $value\n";
      }
      $message .= "---\n";
    }
    $this->message->setBody($message);
    $sent = $this->mailer->send($this->message, $this->failures);
    if ($this->log_errors && !$sent)
    {
      error_log("Error sending mail to '$to' with subject: $subject");
      if ($this->log_message)
        error_log("The message was:\n$message");
    }
    return $sent;
  }

}

// End of class.
