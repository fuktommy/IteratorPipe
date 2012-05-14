<?php
//
// Copyright (c) 2010,2012 Satoshi Fukutomi <info@fuktommy.com>.
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions
// are met:
// 1. Redistributions of source code must retain the above copyright
//    notice, this list of conditions and the following disclaimer.
// 2. Redistributions in binary form must reproduce the above copyright
//    notice, this list of conditions and the following disclaimer in the
//    documentation and/or other materials provided with the distribution.
//
// THIS SOFTWARE IS PROVIDED BY THE AUTHORS AND CONTRIBUTORS ``AS IS'' AND
// ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
// IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
// ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHORS OR CONTRIBUTORS BE LIABLE
// FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
// DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
// OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
// LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
// OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
// SUCH DAMAGE.
//


/**
 * Iterator Pipe.
 *
 * Sample with powerful API.
 * <code>
 *  $src = IteratorPipe::factory(new FooIterator());
 *  $dst = $src->pipe(new FooCommand())->pipe(new BarCommand())->toArray();
 * </code>
 *
 * Sample with usable API.
 * <code>
 *  $src = IteratorPipe::factory(array(6, 7, 8, 9));
 *  $dst = $src->filter(function($k, $v) { return $v % 2 === 0; })
 *       ->map(function($k, $v) { return $v / 2; })
 *       ->toArray(); // array(0 => 3, 2 => 4)
 * </code>
 *
 * @package IteratorPipe
 */
class IteratorPipe implements IteratorAggregate
{
    /**
     * @var array<Iterator>
     */
    private $_inner;

    /**
     * @var IteratorPipe_command
     */
    private $_command;

    /**
     * Constructor.
     * @param Iterator|array $elements
     * @param IteratorPipe_command $command
     */
    private function __construct($elements, IteratorPipe_command $command = null)
    {
        if (is_array($elements)) {
            $this->_inner = new ArrayIterator($elements);
        } elseif ($elements instanceof Iterator) {
            $this->_inner = $elements;
        } elseif ($elements instanceof IteratorAggregate) {
            $this->_inner = $elements->getIterator();
        } elseif ($elements instanceof Traversable) {
            $iter = new IteratorIterator($elements);
            $this->_inner = $iter->getIterator();
        }
        $this->_command = $command;
    }

    /**
     * Factory.
     * @param Iterator|array $elements
     * @return IteratorPipe
     */
    public static function factory($elements)
    {
        return new self($elements);
    }

    /**
     * Get Iterator.
     * @return IteratorPipe_Iterator
     */
    public function getIterator()
    {
        return new IteratorPipe_Iterator($this->_inner, $this->_command);
    }

    /**
     * Return values as an array.
     * @return array
     */
    public function toArray()
    {
        return iterator_to_array($this);
    }

    /**
     * Make new instance with the pipe.
     * @param IteratorPipe_Command|callable $command
     * @return IteratorPipe
     */
    public function pipe($command)
    {
        if ($command instanceof IteratorPipe_Command) {
            return new self($this, $command);
        } elseif (is_callable($command)) {
            return new self($this,
                            new IteratorPipe_CallbackCommand($command));
        } else {
            throw new InvalidArgumentException("{$command} is not command");
        }
    }

    /**
     * Filter. Elements callback returns true are survive.
     * @param callable $callback
     * @return IteratorPipe
     */
    public function filter($callback)
    {
        return $this->pipe(function ($key, $value) use ($callback) {
            if ((bool)$callback($key, $value) === true) {
                return array($key => $value);
            } else {
                return array();
            }
        });
    }

    /**
     * Map callback all elements.
     * @param callable $callback
     * @return IteratorPipe
     */
    public function map($callback)
    {
        return $this->pipe(function ($key, $value) use ($callback) {
            return array($key => $callback($key, $value));
        });
    }

    /**
     * Renumber keys.
     * @return IteratorPipe
     */
    public function renumber()
    {
        return $this->pipe(new IteratorPipe_RenumberCommand());
    }
}


/**
 * Iterator used by IteratorPipe
 * @package IteratorPipe
 */
class IteratorPipe_Iterator implements Iterator
{
    /**
     * @var scalar
     */
    private $_key;

    /**
     * @var mixed
     */
    private $_current;

    /**
     * @var Iterator
     */
    private $_buffer;

    /**
     * @var Iterator
     */
    private $_innrer;

    /**
     * @var bool
     */
    private $_calledFinalize = false;

    /**
     * @var IteratorPipe_Command|null
     */
    private $_command;

    public function __construct(Iterator $inner, $command)
    {
        $this->_inner = $inner;
        $this->_command = $command;
        $this->_buffer = new EmptyIterator();
    }

    public function current()
    {
        return $this->_current;
    }

    public function key()
    {
        return $this->_key;
    }

    public function next()
    {
        $this->_step();
    }

    public function rewind()
    {
        $this->_inner->rewind();
        $this->_step();
    }

    public function valid()
    {
        return $this->_valid;
    }

    private function _popBuffer()
    {
        if (is_array($this->_buffer)) {
            $this->_buffer = new ArrayIterator($this->_buffer);
        }
        if (! $this->_buffer->valid()) {
             $this->_valid = false;
             return;
        }
        $this->_key = $this->_buffer->key();
        $this->_current = $this->_buffer->current();
        $this->_buffer->next();
        $this->_valid = true;
    }

    private function _step()
    {
        if ($this->_buffer->valid()) {
            $this->_popBuffer();
            return;
        }
        $this->_valid = false;
        while ($this->_inner->valid()) {
            $key = $this->_inner->key();
            $value = $this->_inner->current();
            $this->_inner->next();
            $this->_buffer = ($this->_command instanceof IteratorPipe_Command)
                           ? $this->_command->execute($key, $value)
                           : new ArrayIterator(array($key => $value));
            $this->_popBuffer();
            if ($this->_valid) {
                return;
            }
        }
        if (($this->_command instanceof IteratorPipe_Command)
            && (! $this->_calledFinalize)) {
            $this->_buffer = $this->_command->finalize();
            $this->_calledFinalize = true;
        }
        $this->_popBuffer();
    }
}


/**
 * Pipe command.
 * @package IteratorPipe
 */
interface IteratorPipe_Command
{
    /**
     * Execute an element.
     * @param scalar $element
     * @param mixed $element
     * @return Iterator|array
     */
    public function execute($key, $value);

    /**
     * Finalize after execute all element.
     * @return Iterator|array
     */
    public function finalize();
}


/**
 * Renumber index command.
 * @internal
 */
class IteratorPipe_RenumberCommand implements IteratorPipe_Command
{
    /**
     * @var int
     */
    private $_count = 0;

    public function execute($key, $value)
    {
        return array($this->_count++ => $value);
    }

    public function finalize()
    {
        return array();
    }
}


/**
 * Command using callback.
 * @internal
 */
class IteratorPipe_CallbackCommand implements IteratorPipe_Command
{
    /**
     * @var callable
     */
    private $_callback;

    /**
     * @param $callback callable
     */
    public function __construct($callback)
    {
        $this->_callback = $callback;
    }

    public function execute($key, $value)
    {
        return call_user_func($this->_callback, $key, $value);
    }

    public function finalize()
    {
        return array();
    }
}
