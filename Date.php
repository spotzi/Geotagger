<?php
// Date and timezone
define('TIMEZONE_DEFAULT', date_default_timezone_get());
/**
 * Class Date - offers functionality related to dates.
 *
 * @category    Spotzi Webservice
 * @package     Core
 * @author      Ruben Woudenberg <ruben@spotzi.com>
 */
class Date {
        /**
         * Get the default timezone.
         *
         * @return      string                          Default timezone
         */
        public static function getDefaultTimezone() {
                return TIMEZONE_DEFAULT;
        }

        /**
         * Get the default timezone offset.
         * @param       boolean         $textFormat     True to format as text, false otherwise (optional)
         * @param       string          $hourSeparator  Hour separator (optional)
         * @return      mixed                           Default timezone offset
         */
        public static function getDefaultTimezoneOffset($textFormat = false, $hourSeparator = ':') {
                return self::getTimezoneOffset($textFormat, $hourSeparator, self::getDefaultTimezone());
        }

        /**
         * Set the default timezone.
         *
         * @return      boolean                         Default timezone validity
         */
        public static function setDefaultTimezone() {
                return self::setTimeZone(TIMEZONE_DEFAULT);
        }

        /**
         * Get the current timezone.
         *
         * @return      string                          Current timezone
         */
        public static function getTimezone() {
                return date_default_timezone_get();
        }

        /**
         * Get the current timezone offset.
         *
         * @param       boolean         $textFormat     True to format as text, false otherwise (optional)
         * @param       string          $hourSeparator  Hour separator (optional)
         * @param       string          $timezone       Timezone (optional)
         * @return      mixed                           Default timezone offset
         */
        public static function getTimezoneOffset($textFormat = false, $hourSeparator = ':', $timezone = null) {
                // Retrieve the current timezone offset
                $currentTimezone = new DateTimeZone($timezone ? $timezone : self::getTimezone());
                $now = new DateTime('now', $currentTimezone);
                $offset = $now->getOffset();

                // Prepare the offset
                if ($textFormat) $offset = sprintf("%+03d{$hourSeparator}%02u", ($offset / 3600), (abs($offset) % 3600 / 60));

                return $offset;
        }

        /**
         * Set the current timezone.
         *
         * @param       string          $timezone       Current timezone
         * @return      boolean                         Current timezone validity
         */
        public static function setTimezone($timezone) {
                return date_default_timezone_set($timezone);
        }

        /**
         * Format a date.
         *
         * @param       string          $date           Date to format (optional)
         * @param       string          $format         Date format (optional)
         * @param       string          $timezone       Timezone (optional)
         * @return      string                          Formatted date
         */
        public static function format($date = 'now', $format = 'Y-m-d H:i:s', $timezone = null) {
                // Prepare the default and current timezones
                $defaultTimezone =  new DateTimeZone(self::getDefaultTimezone());
                $currentTimezone = new DateTimeZone($timezone ? $timezone : self::getDefaultTimezone());
                if ($date === 'now') $defaultTimezone = $currentTimezone;

                // Prepare the date
                $date = new DateTime($date, $defaultTimezone);
                $date->setTimeZone($currentTimezone);

                // Format and return the date
                return $date->format($format);
        }

        /**
         * Format a timestamp.
         *
         * @param       int             $timestamp      Timestamp to format (optional)
         * @param       string          $format         Date format (optional)
         * @param       string          $timezone       Timezone (optional)
         * @return      string                          Formatted date
         */
        public static function formatTimestamp($timestamp = null, $format = 'Y-m-d H:i:s', $timezone = null) {
                // Prepare the current timezones
                $currentTimezone = new DateTimeZone($timezone ? $timezone : self::getDefaultTimezone());

                // Prepare the date
                $date = new DateTime();
                if ($timestamp) $date->setTimestamp($timestamp);
                $date->setTimeZone($currentTimezone);

                // Format and return the date
                return $date->format($format);
        }

        /**
         * Format a date.
         *
         * @param       string          $date           Date to format (optional)
         * @param       string          $format         Date format (optional)
         * @return      string                          Formatted date
         */
        public static function timezoneFormat($date = 'now', $format = 'Y-m-d H:i:s') {
                return self::format($date, $format, self::getTimezone());
        }

        /**
         * Format a timestamp.
         *
         * @param       int             $timestamp      Timestamp to format (optional)
         * @param       string          $format         Date format (optional)
         * @return      string                          Formatted date
         */
        public static function timezoneFormatTimestamp($timestamp = null, $format = 'Y-m-d H:i:s') {
                return self::formatTimestamp($timestamp, $format, self::getTimezone());
        }

        /**
         * Check if the passed value is a valid timestamp.
         *
         * @param       int             $timestamp      Timestamp to check
         * @return      boolean                         True if the value is a valid timestamp, false otherwise
         */
        public static function isValidTimeStamp($timestamp) {
                return ((string) (int) $timestamp === $timestamp)
                        && ($timestamp <= PHP_INT_MAX)
                        && ($timestamp >= ~PHP_INT_MAX);
        }
}