<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Stream;

/**
 * @covers GuzzleHttp\Psr7\Stream
 */
class StreamTest extends \PHPUnit_Framework_TestCase
{
    public static $isFReadError = false;

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorThrowsExceptionOnInvalidArgument()
    {
        new Stream(true);
    }

    public function testConstructorInitializesProperties()
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertEquals('php://temp', $stream->getMetadata('uri'));
        $this->assertInternalType('array', $stream->getMetadata());
        $this->assertEquals(4, $stream->getSize());
        $this->assertFalse($stream->eof());
        $stream->close();
    }

    public function testStreamClosesHandleOnDestruct()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        unset($stream);
        $this->assertFalse(is_resource($handle));
    }

    public function testConvertsToString()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertEquals('data', (string) $stream);
        $this->assertEquals('data', (string) $stream);
        $stream->close();
    }

    public function testGetsContents()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertEquals('', $stream->getContents());
        $stream->seek(0);
        $this->assertEquals('data', $stream->getContents());
        $this->assertEquals('', $stream->getContents());
    }

    public function testChecksEof()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertFalse($stream->eof());
        $stream->read(4);
        $this->assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize()
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new Stream($handle);
        $this->assertEquals($size, $stream->getSize());
        // Load from cache
        $this->assertEquals($size, $stream->getSize());
        $stream->close();
    }

    public function testEnsuresSizeIsConsistent()
    {
        $h = fopen('php://temp', 'w+');
        $this->assertEquals(3, fwrite($h, 'foo'));
        $stream = new Stream($h);
        $this->assertEquals(3, $stream->getSize());
        $this->assertEquals(4, $stream->write('test'));
        $this->assertEquals(7, $stream->getSize());
        $this->assertEquals(7, $stream->getSize());
        $stream->close();
    }

    public function testProvidesStreamPosition()
    {
        $handle = fopen('php://temp', 'w+');
        $stream = new Stream($handle);
        $this->assertEquals(0, $stream->tell());
        $stream->write('foo');
        $this->assertEquals(3, $stream->tell());
        $stream->seek(1);
        $this->assertEquals(1, $stream->tell());
        $this->assertSame(ftell($handle), $stream->tell());
        $stream->close();
    }

    public function testCanDetachStream()
    {
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);
        $stream->write('foo');
        $this->assertTrue($stream->isReadable());
        $this->assertSame($r, $stream->detach());
        $stream->detach();

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());

        $fail = array($this, 'fail');
        $throws = function ($fn) use ($stream, $fail) {
            try {
                $fn($stream);
                call_user_func($fail);
            } catch (\Exception $e) {}
        };

        $throws(function ($stream) { $stream->read(10); });
        $throws(function ($stream) { $stream->write('bar'); });
        $throws(function ($stream) { $stream->seek(10); });
        $throws(function ($stream) { $stream->tell(); });
        $throws(function ($stream) { $stream->eof(); });
        $throws(function ($stream) { $stream->getSize(); });
        $throws(function ($stream) { $stream->getContents(); });
        $this->assertSame('', (string) $stream);
        $stream->close();
    }

    public function testCloseClearProperties()
    {
        $handle = fopen('php://temp', 'r+');
        $stream = new Stream($handle);
        $stream->close();

        $this->assertFalse($stream->isSeekable());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertNull($stream->getSize());
        $this->assertEmpty($stream->getMetadata());
    }

    public function testDoesNotThrowInToString()
    {
        $s = \GuzzleHttp\Psr7\stream_for('foo');
        $s = new NoSeekStream($s);
        $this->assertEquals('foo', (string) $s);
    }

    public function testStreamReadingWithZeroLength()
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        $this->assertSame('', $stream->read(0));

        $stream->close();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Length parameter cannot be negative
     */
    public function testStreamReadingWithNegativeLength()
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        try {
            $stream->read(-1);
        } catch (\Exception $e) {
            $stream->close();
            throw $e;
        }

        $stream->close();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to read from stream
     */
    public function testStreamReadingFreadError()
    {
        self::$isFReadError = true;
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        try {
            $stream->read(1);
        } catch (\Exception $e) {
            self::$isFReadError = false;
            $stream->close();
            throw $e;
        }

        self::$isFReadError = false;
        $stream->close();
    }
}

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Tests\Psr7\StreamTest;

function fread($handle, $length)
{
    return StreamTest::$isFReadError ? false : \fread($handle, $length);
}
