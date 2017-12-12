<?php

namespace Http\Adapter\Artax\Internal;

use Amp\Artax;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Emitter;
use Amp\Loop;
use Amp\Promise;
use Http\Client\Exception\TransferException;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 stream implementation that converts an `Amp\ByteStream\InputStream` into a PSR-7 compatible stream.
 *
 * @internal
 */
class ResponseStream implements StreamInterface, AsyncReadableStreamInterface
{
    private $buffer = '';
    private $position = 0;
    private $eof = false;

    private $body;
    private $cancellationTokenSource;
    private $async = false;

    /**
     * @param InputStream             $body                    HTTP response stream to wrap.
     * @param CancellationTokenSource $cancellationTokenSource Cancellation source bound to the request to abort it.
     */
    public function __construct(InputStream $body, CancellationTokenSource $cancellationTokenSource, $async = true)
    {
        $this->body = $body;
        $this->cancellationTokenSource = $cancellationTokenSource;
        $this->async = $async;
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
        return !$this->async;
    }

    public function read($length)
    {
        if ($this->async) {
            throw new \RuntimeException('Stream is only readable in async');
        }

        return Promise\wait($this->readAsync($length));
    }

    public function getContents()
    {
        if ($this->async) {
            throw new \RuntimeException('Stream is only readable in async');
        }

        return Promise\wait($this->getContentsAsync());
    }

    public function readAsync($length)
    {
        return \Amp\call(function () use($length) {
            if ($this->eof) {
                return '';
            }

            while (\strlen($this->buffer) < $length) {
                $readed = yield $this->body->read();

                if ($readed === null) {
                    $this->eof = true;
                    break;
                }

                $this->buffer .= $readed;
            }

            $read = \substr($this->buffer, 0, $length);
            $this->buffer = (string) \substr($this->buffer, $length);
            $this->position += \strlen($read);

            return $read;
        });
    }

    public function getContentsAsync()
    {
        return \Amp\call(function () {
            $contents = '';

            while (!$this->eof()) {
                $contents .= yield $this->readAsync(8192 * 8);
            }

            return $contents;
        });
    }

    public function isReadableAsync()
    {
        return $this->async;
    }

    public function getMetadata($key = null)
    {
        return $key === null ? [] : null;
    }
}
