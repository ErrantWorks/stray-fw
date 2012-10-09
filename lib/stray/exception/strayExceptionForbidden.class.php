<?php
/**
 * @brief This is the forbidenn error exception class.
 * @author nekith@gmail.com
 */

class strayExceptionForbidden extends strayException
{
  /**
   * Get the exception message.
   * @return string message
   * @final
   */
  final public function Display()
  {
    return 'Forbidden: ' . parent::getMessage();
  }
}
