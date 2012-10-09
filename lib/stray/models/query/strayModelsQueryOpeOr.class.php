<?php
/**
 * @brief Allows to perform a "OR" logical operator in a query.
 * @author nekith@gmail.com
 */
class strayModelsQueryOpeOr extends strayModelsQueryAOpe
{
  public $left;
  public $right;

  /**
   * Construct.
   * @param mixed $left left operand
   * @param mixed $right right operand
   */
  public function __construct($left = null, $right = null)
  {
    $this->left = $left;
    $this->right = $right;
  }

  /**
   * Get the operator corresponding SQL code.
   * @return string SQL
   */
  public function ToSql()
  {
    return '('
      . (true === ($this->left instanceof strayModelsQueryAOpe) ? $this->left->ToSql() : $this->left)
      . ' OR '
      . (true === ($this->right instanceof strayModelsQueryAOpe) ? $this->right->ToSql() : $this->right)
      . ')';
  }
}
