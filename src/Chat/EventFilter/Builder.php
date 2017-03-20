<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\EventFilter;

class Builder
{
    private $parser;
    private $compiler;
    /**
     * @var Factory
     */
    private $factory;

    public function __construct(Parser $parser, Compiler $compiler, Factory $factory)
    {
        $this->parser = $parser;
        $this->compiler = $compiler;
        $this->factory = $factory;
    }

    public function build(string $filter, callable $callback)
    {
        $conditions = $this->parser->parse($filter);
        list($predicates, $types, $rooms) = $this->compiler->compile($conditions);

        return $this->factory->build($filter, $predicates, $types, $rooms, $callback);
    }
}
