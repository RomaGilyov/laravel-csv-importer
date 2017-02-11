<?php namespace RGilyov\CsvImporter;

class ClosureRequiredFilter extends BaseRequiredFilter
{
    /**
     * @var \Closure
     */
    protected $closure;

    /**
     * ClosureRequiredFilter constructor.
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
    protected function filter(array $csvHeaders)
    {
        return $this->closure->__invoke($csvHeaders);
    }
}