<?php namespace RGilyov\CsvImporter;

class ClosureValidationFilter extends BaseValidationFilter
{
    /**
     * @var \Closure
     */
    protected $closure;

    /**
     * @var bool
     */
    public $global = true;

    /**
     * @var string
     */
    protected $name = 'filter';

    /**
     * ClosureValidationFilter constructor.
     * @param \Closure $closure
     */
    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * @param array $value
     * @return bool
     */
    public function filter($value)
    {
        return $this->closure->__invoke($value);
    }
}