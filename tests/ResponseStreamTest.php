<?php

namespace Http\Adapter\Artax\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Emitter;
use Http\Adapter\Artax\Internal\ResponseStream;
use PHPUnit\Framework\TestCase;
use function Amp\Iterator\fromIterable;

class ResponseStreamTest extends TestCase
{
    public function testNotSeekable()
    {
        $stream = new ResponseStream(new InMemoryStream(), new CancellationTokenSource());
        $this->assertFalse($stream->isSeekable());

        $this->expectException(\RuntimeException::class);
        $stream->seek(0);
    }

    public function testNotRewindable()
    {
        $stream = new ResponseStream(new InMemoryStream(), new CancellationTokenSource());

        $this->expectException(\RuntimeException::class);
        $stream->rewind();
    }

    public function testNotWritable()
    {
        $stream = new ResponseStream(new InMemoryStream(), new CancellationTokenSource());
        $this->assertFalse($stream->isWritable());

        $this->expectException(\RuntimeException::class);
        $stream->write('');
    }

    public function testReadSlowStream()
    {
        $inputStream = new IteratorStream(fromIterable(['a', 'b', 'c'], 100));
        $stream = new ResponseStream($inputStream, new CancellationTokenSource(), false);
        $this->assertTrue($stream->isReadable());

        $this->assertSame('abc', (string) $stream);

        // As the stream isn't rewindable, we get an empty result here.
        $this->assertSame('', (string) $stream);

        $this->assertSame(3, $stream->tell());

        $this->assertSame('', $stream->read(8192));
    }

    public function testReadAfterClose()
    {
        $inputStream = new IteratorStream(fromIterable(['a', 'b', 'c'], 100));
        $stream = new ResponseStream($inputStream, new CancellationTokenSource());

        $stream->close();

        $this->expectException(\RuntimeException::class);
        $stream->read(8192);
    }

    public function testStringCastAfterClose()
    {
        $inputStream = new IteratorStream(fromIterable(['a', 'b', 'c'], 100));
        $stream = new ResponseStream($inputStream, new CancellationTokenSource());

        $stream->close();

        $this->assertSame('', (string) $stream);
    }

    public function testReadAfterCancel()
    {
        $emitter = new Emitter();
        $emitter->fail(new CancelledException());
        $inputStream = new IteratorStream($emitter->iterate());
        $stream = new ResponseStream($inputStream, new CancellationTokenSource());

        $this->expectException(\RuntimeException::class);
        $stream->read(8192);
    }

    public function testReadAfterDetach()
    {
        $inputStream = new IteratorStream(fromIterable(['a', 'b', 'c'], 100));
        $stream = new ResponseStream($inputStream, new CancellationTokenSource());

        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->read(8192);
    }

    public function testMetadata()
    {
        $stream = new ResponseStream(new InMemoryStream(), new CancellationTokenSource());
        $this->assertNull($stream->getMetadata('foobar'));
        $this->assertInternalType('array', $stream->getMetadata());
    }

    public function testSize()
    {
        $stream = new ResponseStream(new InMemoryStream(), new CancellationTokenSource());
        $this->assertNull($stream->getSize());
    }
}
