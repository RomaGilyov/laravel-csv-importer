<?php namespace RGilyov\CsvImporter;

class ClosureHeadersFilter extends BaseHeadersFilter
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
     * ClosureHeadersFilter constructor.
     * @param \Closure $closure
     */
    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * @param array $csvHeaders
     * @return bool
     */
    public function filter(array $csvHeaders)
    {
        return $this->closure->__invoke($csvHeaders);
    }
}