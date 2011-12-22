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

require_once 'PHPUnit/Framework.php';

error_reporting(E_ALL | E_STRICT);

require_once 'FilterWrapper.php';


class AddOne implements FilterWrapper_Filter
{
    public function filter($key, $value)
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


class Counter implements FilterWrapper_Filter
{
    private $count = 0;

    public function filter($key, $value)
    {
        $this->count++;
        return array();
    }

    public function finalize()
    {
        return array($this->count);
    }
}


/**
 * Test of FilterWrapper.
 * @package FilterWrapper
 */
class FilterWrapperTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test without filters.
     */
    public function testWapperWithoutFilters()
    {
        $fw = FilterWrapper::factory(array(11, 12, 13));
        $this->assertSame(array(11, 12, 13), iterator_to_array($fw));
    }

    /**
     * Test with counter.
     */
    public function testWrapperWithCounter()
    {
        $fw = FilterWrapper::factory(array(11, 12, 13))
            ->filter(new Counter());
        $this->assertSame(array(3), $fw->toArray());
    }

    /**
     * Test with add one filter.
     */
    public function testWrapperWithAddOne()
    {
        $array = array(
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
        $fw = FilterWrapper::factory(new ArrayIterator($array))
            ->filter(new AddOne());
        $this->assertSame($expected, $fw->toArray());
    }

    /**
     * Test with 2 filters.
     */
    public function testWrapperWithTwoFilters()
    {
        $fw = FilterWrapper::factory(array(11, 12, 13))
            ->filter(new AddOne())
            ->filter(new Counter());
        $this->assertSame(array(6), $fw->toArray());
    }

}
