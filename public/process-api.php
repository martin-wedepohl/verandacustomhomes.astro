<?php
/**
 * Process the API calls which are sent in a JSON string
 *
 * PHP Version 7
 *
 * @category Api
 * @package  Code
 * @author   Martin Wedepohl <martin@orchardrecovery.com>
 * @license  https://ox.o-connect.ca Proprietary
 * @link     https://ox.o-connect.ca/Api/process-api.php
 */

 /**
  * PHP can't read json data directly
  */
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
// Receive the RAW post data.
$content = trim(file_get_contents("php://input"));
// Decode the JSON string into a named array.
$args = json_decode($content, true);

try {
    // Default for invalid data
    $result = [
        'status'  => 406,
        'message' => 'No content available for mode requested',
    ];

    // Check what the user wants to do
    $mode   = isset($args['mode'])   ? $args['mode']   : null;
    $action = isset($args['action']) ? $args['action'] : null;
    $data   = isset($args['data'])   ? $args['data']   : null;

    if (null !== $mode && null !== $action && null !== $data) {
        if ('mailer' === $mode) {
            if ('send-email' === $action) {
                $result = sendEmail($data);
            }
        }
    }
} catch (Exception $e) {
    $result = [
        'message' => $e->getMessage(),
        'status'  => 400,
    ];
}

// Check if we have an error and set the appropriate header
if (isset($result['status'])) {
    switch ($result['status']) {
    case 400:
        header('HTTP/1.1 400 Bad Request');
        break;
    case 406:
        header('HTTP/1.1 406 Not Acceptable');
        break;
    default:
    }
}

// headers for not caching the results
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// headers to tell that result is JSON
header('Content-type: application/json');

exit(json_encode($result));

/**
 * Clean the string if it contains bad content for emailing.
 *
 * @param string $string The string to be cleaned.
 *
 * @return string The cleaned string.
 */
function cleanString($string) {
    $bad = array("content-type", "bcc:", "to:", "cc:", "href");
    return str_replace($bad, '', $string);
}

/**
 * Send Email.
 *
 * The input data will contain the following:
 *   name    - The name of the person sending the email.
 *   email   - The email address of the person sending the email.
 *   subject - The subject of the email being sent.
 *   message - THe message body of the email being sent.
 *
 * @param array $data The data array.
 *
 * @return array The result of the emailing.
 */
function sendEmail($data)
{
    try {
        $name    = isset($data['name'])    ? $data['name']    : null;
        $email   = isset($data['email'])   ? $data['email']   : null;
        $subject = isset($data['subject']) ? $data['subject'] : null;
        $message = isset($data['message']) ? $data['message'] : null;

        $err = '';
        if (null === $name) {
            $err .= 'Name missing<br>';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err .= 'Email invalid<br>';
        }
        if (null === $subject) {
            $err .= 'Subject missing<br>';
        }
        if (null === $message) {
            $err .= 'Message missing<br>';
        }
        if ('' !== $err) {
            throw new Exception($err);
        }

        $veranda_email  = 'info@verandacustomhomes.ca';
        $email_to       = $veranda_email;
        $subject        = cleanString($subject);
        $email_message  = "Information request\n\n";
        $email_message .= "Name: " . cleanString($name) . "\r\n";
        $email_message .= "Email: " . cleanString($email) . "\r\n";
        $email_message .= "Message: " . cleanString($message) . "\r\n";

        // If coming from Windows computer replace . at beginning of line with .. as per RFC
        $email_message = str_replace("\n.", "\n..", $email_message);

        // Truncate message to 70 characters as per RFC
        $email_message = wordwrap($message, 70, "\r\n");

        $headers = 'From: ' . $veranda_email . "\r\n" .
                   'Reply-To: ' . $email . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
    
        if (!mail($email_to, $subject, $email_message, $headers)) {
            throw new Exception('Unable to send email');
        }

        return ['message' => 'Email successfully sent'];

    } catch (Exception $e ) {
        throw new Exception($e->getMessage());
    }
}