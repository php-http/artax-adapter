<?php

namespace Http\Adapter\Artax\Internal;

use Amp\Artax;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Emitter;
use Amp\Promise;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 stream implementation that converts an `Amp\ByteStream\InputStream` into a PSR-7 compatible stream.
 *
 * @internal
 */
class ResponseStream implements StreamInterface
{
    private $buffer = '';
    private $position = 0;
    private $eof = false;

    private $body;
    private $cancellationTokenSource;

    /**
     * @param InputStream             $body                    HTTP response stream to wrap.
     * @param CancellationTokenSource $cancellationTokenSource Cancellation source bound to the request to abort it.
     */
    public function __construct(InputStream $body, CancellationTokenSource $cancellationTokenSource)
    {
        $this->body = $body;
        $this->cancellationTokenSource = $cancellationTokenSource;
    }

    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        $this->cancellationTokenSource->cancel();

        $emitter = new Emitter();
        $emitter->fail(new Artax\HttpException('The stream has been closed'));
        $this->body = new IteratorStream($emitter->iterate());
    }

    public function detach()
    {
        $this->close();
    }

    public function getSize()
    {
        return null;
    }

    public function tell()
    {
        return $this->position;
    }

    public function eof()
    {
        return $this->eof;
    }

    public function isSeekable()
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException('Stream is not seekable');
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
        throw new \RuntimeException('Stream is not writable');
    }

    public function isReadable()
    {
        return true;
    }

    public function read($length)
    {
        if ($this->eof) {
            return '';
        }

        if ('' === $this->buffer) {
            try {
                $this->buffer = Promise\wait($this->body->read());
            } catch (Artax\HttpException $e) {
                throw new \RuntimeException('Reading from the stream failed', 0, $e);
            } catch (CancelledException $e) {
                throw new \RuntimeException('Reading from the stream failed', 0, $e);
            }

            if (null === $this->buffer) {
                $this->eof = true;

                return '';
            }
        }

        $read = \substr($this->buffer, 0, $length);
        $this->buffer = (string) \substr($this->buffer, $length);
        $this->position += \strlen($read);

        return $read;
    }

    public function getContents()
    {
        $buffer = '';

        while (!$this->eof()) {
            $buffer .= $this->read(8192 * 8);
        }

        return $buffer;
    }

    public function getMetadata($key = null)
    {
        return null === $key ? [] : null;
    }
}
