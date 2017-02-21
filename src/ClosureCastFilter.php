<?php namespace RGilyov\CsvImporter;

class ClosureCastFilter extends BaseCastFilter
{
    /**
     * @var \Closure
     */
    protected $closure;

    /**
     * @var string
     */
    protected $name = 'filter';

    /**
     * ClosureCastFilter constructor.
     * @param \Closure $closure
     */
    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function filter($value)
    {
        return $this->closure->__invoke($value);
    }
}