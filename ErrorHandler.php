<?php
// Activate the custom error handler
ErrorHandler::setCustomErrorHandling();
// Display errors
ini_set('display_errors', '1');

include "Date.php";

// API exception
class ApiException extends Exception {
        // HTTP status code
        protected $statusCode = null;

        /**
         * Constructor.
         *
         * @param       int             $status         HTTP status code
         * @param       string          $message        Exception message
         * @param       int             $code           Exception code
         * @param       Exception       $previous       Previous exception used for exception chaining
         */
        public function __construct($status = 0, $message = '', $code = 0, $previous = null) {
                // Set the status code
                $this->statusCode = $status;

                // Construct the parent class
                parent::__construct(trim($message), $code, $previous);
        }

        /**
         * Retrieve the HTTP status code belonging to this exception.
         *
         * @return      int                             HTTP status code
         */
        public function getStatusCode() {
                return $this->statusCode;
        }
}

/**
 * Class ErrorHandler - offers error handling support functions.
 *
 * @category    Spotzi Webservice
 * @package     Core
 * @author      Ruben Woudenberg <ruben@spotzi.com>
 */
class ErrorHandler {
        /**
         * Throw an error.
         */
        public static function error() {
                // Retrieve the function arguments
                $args = func_get_args();

                // Retrieve the error status
                $errorStatus = array_shift($args);
                // Retrieve the error index
                $errorArg = reset($args);
                $errorIndex = (is_integer($errorArg) && $errorArg >= 0 ? array_shift($args) : 0);

                // Retrieve the debug backtrace
                $errorTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                // Prepare the error message
                $errorMsg = call_user_func_array('sprintf', $args);

                // Handle the error
                self::handle($errorStatus, $errorMsg, $errorTrace[$errorIndex]['file'], $errorTrace[$errorIndex]['line']);
        }

        /**
         * Handle an error.
         *
         * @param       int             $errorStatus    Error status code
         * @param       string          $errorMsg       Error message
         * @param       string          $errorFile      Error script file
         * @param       int             $errorLine      Error script line
         * @throws      ApiException                    API exception
         */
        public static function handle($errorStatus, $errorMsg, $errorFile, $errorLine) {
                // Build the complete error message
                $mailMsg = '<b>--- Spotzi ErrorHandler ---</b>' . PHP_EOL . PHP_EOL;
                $mailMsg .= 'Date: '. Date::format() . PHP_EOL;
                $mailMsg .= 'Error status: ' . $errorStatus . PHP_EOL;
                $mailMsg .= 'Error message: ' . $errorMsg . PHP_EOL;
                $mailMsg .= 'Script name: ' . $errorFile . PHP_EOL;
                $mailMsg .= 'Line number: ' . $errorLine . PHP_EOL;
                //$mailMsg .= 'Request referer: ' . REQUEST_REFERER . PHP_EOL;
                //$mailMsg .= 'Request URL: ' . URL_BASE . ltrim(REQUEST_URI, '/') . PHP_EOL;
                if (isset($_SERVER['HTTP_USER_AGENT'])) $mailMsg .= 'User agent: ' . $_SERVER['HTTP_USER_AGENT'];

                // Determine whether debug mode is active
                if (debugMode()) {
                        // In case debug mode is active, set the error message as the frontend message
                        debugPrint($mailMsg);
                } else {
                        // Send the error email when needed
                        if (HttpStatus::emailStatus($errorStatus)) {
                                // Prepare the error mailer
                                Mail::addMailer(EMAIL_HOST, EMAIL_PORT, EMAIL_ERROR_FROM,
                                                EMAIL_ERROR_FROM_PASSWORD, BRAND_PRODUCT);
                                // Send the error email
                                Mail::send(EMAIL_ERROR_RECIPIENT, EMAIL_ERROR_FROM,
                                           EMAIL_ERROR_SUBJECT, nl2br($mailMsg));
                        }

                        throw new ApiException($errorStatus, $errorMsg);
                }
        }

        /**
         * Activate the default error handler.
         */
        public static function setDefaultErrorHandling() {
                restore_error_handler();
        }

        /**
         * Activate our custom error handler.
         */
        public static function setCustomErrorHandling() {
                // Activate our custom error handler
                set_error_handler(array(get_class(), 'handle'));

                // Set the error handling level depending on debug/production mode
                error_reporting(debugMode() ? E_ALL | E_STRICT : E_ALL);
        }
}
