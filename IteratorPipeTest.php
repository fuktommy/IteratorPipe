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

error_reporting(E_ALL | E_STRICT);

require_once __DIR__ . '/IteratorPipe.php';


class AddOne implements IteratorPipe_Command
{
    public function execute($key, $value)
    {
        return array(
            $key => $value,
            $key . 'x' => $value + 1,
        );
    }

    public function finalize()
    {
        return array();
    }
}


class Counter implements IteratorPipe_Command
{
    private $_count = 0;

    public function execute($key, $value)
    {
        $this->_count++;
        return array();
    }

    public function finalize()
    {
        return array($this->_count);
    }
}


/**
 * Test of IteratorPipe.
 */
class IteratorPipeTest extends PHPUnit_Framework_TestCase
{
    public function testWithoutPipes()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13));
        $this->assertSame(array(11, 12, 13), iterator_to_array($pipe));
    }

    public function testWithCounter()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13))
              ->pipe(new Counter());
        $this->assertSame(array(3), $pipe->toArray());
    }

    public function testCallback()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13))
              ->pipe(function ($k, $v) { return array($k => $v * 2); });
        $this->assertSame(array(22, 24, 26), $pipe->toArray());

    }

    public function testWithAddOne()
    {
        $source = array(
            'a' => 11,
            'b' => 21,
            'c' => 31,
        );
        $expected = array(
            'a' => 11,
            'ax' => 12,
            'b' => 21,
            'bx' => 22,
            'c' => 31,
            'cx' => 32,
        );
        $pipe = IteratorPipe::factory(new ArrayIterator($source))
              ->pipe(new AddOne());
        $this->assertSame($expected, $pipe->toArray());
    }

    public function testWithTwoCommands()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13))
              ->pipe(new AddOne())
              ->pipe(new Counter());
        $this->assertSame(array(6), $pipe->toArray());
    }

    public function testFilter()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13))
              ->filter(function($k, $v) {return $v % 2 === 0; });
        $this->assertSame(array(1 => 12), $pipe->toArray());
    }

    public function testMap()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13))
              ->map(function($k, $v) {return $v * 2; });
        $this->assertSame(array(22, 24, 26), $pipe->toArray());
    }

    public function testFilterAndMap()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13, 14))
              ->filter(function($k, $v) {return $v % 2 === 0; })
              ->map(function($k, $v) {return $v / 2; });
        $this->assertSame(array(1 => 6, 3 => 7), $pipe->toArray());
    }

    public function testRenumber()
    {
        $pipe = IteratorPipe::factory(array(11, 12, 13, 14))
              ->filter(function($k, $v) {return $v % 2 === 0; })
              ->renumber();
        $this->assertSame(array(12, 14), $pipe->toArray());
    }
}
