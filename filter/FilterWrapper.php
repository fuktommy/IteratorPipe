<?php
//
// Copyright (c) 2010 Satoshi Fukutomi <info@fuktommy.com>.
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
// $Id$
//


/**
 * Filter Wrapper.
 *
 * $fw = FilterWrapper::factory(new FooIterator());
 * $fw = $fw->filter(new FooFilter())->filter(new BarFilter());
 * $res = $fw->toArray();
 *
 * @package FilterWrapper
 */
class FilterWrapper implements IteratorAggregate
{
    /**
     * @var array  array of innter iterator.
     */
    private $inner;

    /**
     * @var FilterWrapper_Filter
     */
    private $filter;

    /**
     * Constructor.
     * @param mixed $elements  iterator or array
     * @param FilterWrapper_Filter $filter
     */
    private function __construct($elements, $filter = null)
    {
        if (is_array($elements)) {
            $this->inner = new ArrayIterator($elements);
        } elseif ($elements instanceof Iterator) {
            $this->inner = $elements;
        } elseif ($elements instanceof IteratorAggregate) {
            $this->inner = $elements->getIterator();
        } elseif ($elements instanceof Traversable) {
            $iter = new IteratorIterator($elements);
            $this->inner = $iter->getIterator();
        }
        $this->filter = $filter;
    }

    /**
     * Factory.
     * @param mixed $elements  iterator or array
     * @return FilterWrapper
     */
    public static function factory($elements)
    {
        return new self($elements);
    }

    /**
     * Get Iterator.
     * @return FilterWrapper_Iterator
     */
    public function getIterator()
    {
        return new FilterWrapper_Iterator($this->inner, $this->filter);
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
     * Make new wrapper with the filter.
     * @param FilterWrapper_Filter $filter
     * @return FilterWrapper
     */
    public function filter(FilterWrapper_Filter $filter)
    {
        return new self($this, $filter);
    }
}


/**
 * Iterator Used by Filter Wrapper.
 * @package FilterWrapper
 */
class FilterWrapper_Iterator implements Iterator
{
    /**
     * @var scalar
     */
    private $key;

    /**
     * @var mixed
     */
    private $current;

    /**
     * @var Iterator
     */
    private $buffer;

    /**
     * @var Iterator
     */
    private $innrer;

    /**
     * @var bool
     */
    private $calledFinalize = false;

    /**
     * @var mixed  FilterWrapper_Filter or null
     */
    private $filter;

    public function __construct(Iterator $inner, $filter)
    {
        $this->inner = $inner;
        $this->filter = $filter;
        $this->buffer = new EmptyIterator();
    }

    public function current()
    {
        return $this->current;
    }

    public function key()
    {
        return $this->key;
    }

    public function next()
    {
        $this->_step();
    }

    public function rewind()
    {
        $this->inner->rewind();
        $this->_step();
    }

    public function valid()
    {
        return $this->valid;
    }

    private function _popBuffer()
    {
        if (is_array($this->buffer)) {
            $this->buffer = new ArrayIterator($this->buffer);
        }
        if (! $this->buffer->valid()) {
             $this->valid = false;
             return;
        }
        $this->key = $this->buffer->key();
        $this->current = $this->buffer->current();
        $this->buffer->next();
        $this->valid = true;
    }

    private function _step()
    {
        if ($this->buffer->valid()) {
            $this->_popBuffer();
            return;
        }
        $this->valid = false;
        while ($this->inner->valid()) {
            $key = $this->inner->key();
            $value = $this->inner->current();
            $this->inner->next();
            $this->buffer = ($this->filter)
                          ? $this->filter->filter($key, $value)
                          : new ArrayIterator(array($key => $value));
            $this->_popBuffer();
            if ($this->valid) {
                return;
            }
        }
        if ($this->filter && (! $this->calledFinalize)) {
            $this->buffer = $this->filter->finalize();
            $this->calledFinalize = true;
        }
        $this->_popBuffer();
    }
}


/**
 * Filter for Filter Wrapper.
 * @package FilterWrapper
 */
interface FilterWrapper_Filter
{
    /**
     * Filter an element.
     * @param scalar $element
     * @param mixed $element
     * @return mixed  array or Iterator.
     */
    public function filter($key, $value);

    /**
     * Finalize after filter all element.
     * @return mixed  array or Iterator.
     */
    public function finalize();
}
