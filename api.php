<?php
namespace Psr\Http\Message {
    interface RequestFactoryInterface
    {
        public function createRequest(string $method, $uri): RequestInterface;
    }
}
namespace Psr\Http\Message {
    interface ResponseFactoryInterface
    {
        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;
    }
}
namespace Psr\Http\Message {
    interface ServerRequestFactoryInterface
    {
        public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface;
    }
}
namespace Psr\Http\Message {
    interface StreamFactoryInterface
    {
        public function createStream(string $content = ''): StreamInterface;
        public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface;
        public function createStreamFromResource($resource): StreamInterface;
    }
}
namespace Psr\Http\Message {
    interface UploadedFileFactoryInterface
    {
        public function createUploadedFile(
            StreamInterface $stream,
            ?int $size = null,
            int $error = \UPLOAD_ERR_OK,
            ?string $clientFilename = null,
            ?string $clientMediaType = null
        ): UploadedFileInterface;
    }
}
namespace Psr\Http\Message {
    interface UriFactoryInterface
    {
        public function createUri(string $uri = ''): UriInterface;
    }
}
namespace Psr\Http\Message {
    interface MessageInterface
    {
        public function getProtocolVersion(): string;
        public function withProtocolVersion(string $version): MessageInterface;
        public function getHeaders(): array;
        public function hasHeader(string $name): bool;
        public function getHeader(string $name): array;
        public function getHeaderLine(string $name): string;
        public function withHeader(string $name, $value): MessageInterface;
        public function withAddedHeader(string $name, $value): MessageInterface;
        public function withoutHeader(string $name): MessageInterface;
        public function getBody(): StreamInterface;
        public function withBody(StreamInterface $body): MessageInterface;
    }
}
namespace Psr\Http\Message {
    interface RequestInterface extends MessageInterface
    {
        public function getRequestTarget(): string;
        public function withRequestTarget(string $requestTarget): RequestInterface;
        public function getMethod(): string;
        public function withMethod(string $method): RequestInterface;
        public function getUri(): UriInterface;
        public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface;
    }
}
namespace Psr\Http\Message {
    interface ResponseInterface extends MessageInterface
    {
        public function getStatusCode(): int;
        public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;
        public function getReasonPhrase(): string;
    }
}
namespace Psr\Http\Message {
    interface ServerRequestInterface extends RequestInterface
    {
        public function getServerParams(): array;
        public function getCookieParams(): array;
        public function withCookieParams(array $cookies): ServerRequestInterface;
        public function getQueryParams(): array;
        public function withQueryParams(array $query): ServerRequestInterface;
        public function getUploadedFiles(): array;
        public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface;
        public function getParsedBody();
        public function withParsedBody($data): ServerRequestInterface;
        public function getAttributes(): array;
        public function getAttribute(string $name, $default = null);
        public function withAttribute(string $name, $value): ServerRequestInterface;
        public function withoutAttribute(string $name): ServerRequestInterface;
    }
}
namespace Psr\Http\Message {
    interface StreamInterface
    {
        public function __toString(): string;
        public function close(): void;
        public function detach();
        public function getSize(): ?int;
        public function tell(): int;
        public function eof(): bool;
        public function isSeekable(): bool;
        public function seek(int $offset, int $whence = SEEK_SET): void;
        public function rewind(): void;
        public function isWritable(): bool;
        public function write(string $string): int;
        public function isReadable(): bool;
        public function read(int $length): string;
        public function getContents(): string;
        public function getMetadata(?string $key = null);
    }
}
namespace Psr\Http\Message {
    interface UploadedFileInterface
    {
        public function getStream(): StreamInterface;
        public function moveTo(string $targetPath): void;
        public function getSize(): ?int;
        public function getError(): int;
        public function getClientFilename(): ?string;
        public function getClientMediaType(): ?string;
    }
}
namespace Psr\Http\Message {
    interface UriInterface
    {
        public function getScheme(): string;
        public function getAuthority(): string;
        public function getUserInfo(): string;
        public function getHost(): string;
        public function getPort(): ?int;
        public function getPath(): string;
        public function getQuery(): string;
        public function getFragment(): string;
        public function withScheme(string $scheme): UriInterface;
        public function withUserInfo(string $user, ?string $password = null): UriInterface;
        public function withHost(string $host): UriInterface;
        public function withPort(?int $port): UriInterface;
        public function withPath(string $path): UriInterface;
        public function withQuery(string $query): UriInterface;
        public function withFragment(string $fragment): UriInterface;
        public function __toString(): string;
    }
}
namespace Psr\Http\Server {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    interface RequestHandlerInterface
    {
        public function handle(ServerRequestInterface $request): ResponseInterface;
    }
}
namespace Psr\Http\Server {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    interface MiddlewareInterface
    {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
    }
}
namespace Nyholm\Psr7\Factory {
    use Nyholm\Psr7\{Request, Response, ServerRequest, Stream, UploadedFile, Uri};
    use Psr\Http\Message\{RequestFactoryInterface, RequestInterface, ResponseFactoryInterface, ResponseInterface, ServerRequestFactoryInterface, ServerRequestInterface, StreamFactoryInterface, StreamInterface, UploadedFileFactoryInterface, UploadedFileInterface, UriFactoryInterface, UriInterface};
    class Psr17Factory implements RequestFactoryInterface, ResponseFactoryInterface, ServerRequestFactoryInterface, StreamFactoryInterface, UploadedFileFactoryInterface, UriFactoryInterface
    {
        public function createRequest(string $method, $uri): RequestInterface
        {
            return new Request($method, $uri);
        }
        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
        {
            if (2 > \func_num_args()) {
                // This will make the Response class to use a custom reasonPhrase
                $reasonPhrase = null;
            }
            return new Response($code, [], null, '1.1', $reasonPhrase);
        }
        public function createStream(string $content = ''): StreamInterface
        {
            return Stream::create($content);
        }
        public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
        {
            if ('' === $filename) {
                throw new \RuntimeException('Path cannot be empty');
            }
            if (false === $resource = @\fopen($filename, $mode)) {
                if ('' === $mode || false === \in_array($mode[0], ['r', 'w', 'a', 'x', 'c'], true)) {
                    throw new \InvalidArgumentException(\sprintf('The mode "%s" is invalid.', $mode));
                }
                throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $filename, \error_get_last()['message'] ?? ''));
            }
            return Stream::create($resource);
        }
        public function createStreamFromResource($resource): StreamInterface
        {
            return Stream::create($resource);
        }
        public function createUploadedFile(StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): UploadedFileInterface
        {
            if (null === $size) {
                $size = $stream->getSize();
            }
            return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
        }
        public function createUri(string $uri = ''): UriInterface
        {
            return new Uri($uri);
        }
        public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
        {
            return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\MessageInterface;
    use Psr\Http\Message\StreamInterface;
    trait MessageTrait
    {
        private $headers = [];
        private $headerNames = [];
        private $protocol = '1.1';
        private $stream;
        public function getProtocolVersion(): string
        {
            return $this->protocol;
        }
        public function withProtocolVersion($version): MessageInterface
        {
            if (!\is_scalar($version)) {
                throw new \InvalidArgumentException('Protocol version must be a string');
            }
            if ($this->protocol === $version) {
                return $this;
            }
            $new = clone $this;
            $new->protocol = (string) $version;
            return $new;
        }
        public function getHeaders(): array
        {
            return $this->headers;
        }
        public function hasHeader($header): bool
        {
            return isset($this->headerNames[\strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')]);
        }
        public function getHeader($header): array
        {
            if (!\is_string($header)) {
                throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
            }
            $header = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            if (!isset($this->headerNames[$header])) {
                return [];
            }
            $header = $this->headerNames[$header];
            return $this->headers[$header];
        }
        public function getHeaderLine($header): string
        {
            return \implode(', ', $this->getHeader($header));
        }
        public function withHeader($header, $value): MessageInterface
        {
            $value = $this->validateAndTrimHeader($header, $value);
            $normalized = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            $new = clone $this;
            if (isset($new->headerNames[$normalized])) {
                unset($new->headers[$new->headerNames[$normalized]]);
            }
            $new->headerNames[$normalized] = $header;
            $new->headers[$header] = $value;
            return $new;
        }
        public function withAddedHeader($header, $value): MessageInterface
        {
            if (!\is_string($header) || '' === $header) {
                throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
            }
            $new = clone $this;
            $new->setHeaders([$header => $value]);
            return $new;
        }
        public function withoutHeader($header): MessageInterface
        {
            if (!\is_string($header)) {
                throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
            }
            $normalized = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            if (!isset($this->headerNames[$normalized])) {
                return $this;
            }
            $header = $this->headerNames[$normalized];
            $new = clone $this;
            unset($new->headers[$header], $new->headerNames[$normalized]);
            return $new;
        }
        public function getBody(): StreamInterface
        {
            if (null === $this->stream) {
                $this->stream = Stream::create('');
            }
            return $this->stream;
        }
        public function withBody(StreamInterface $body): MessageInterface
        {
            if ($body === $this->stream) {
                return $this;
            }
            $new = clone $this;
            $new->stream = $body;
            return $new;
        }
        private function setHeaders(array $headers): void
        {
            foreach ($headers as $header => $value) {
                if (\is_int($header)) {
                    // If a header name was set to a numeric string, PHP will cast the key to an int.
                    // We must cast it back to a string in order to comply with validation.
                    $header = (string) $header;
                }
                $value = $this->validateAndTrimHeader($header, $value);
                $normalized = \strtr($header, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
                if (isset($this->headerNames[$normalized])) {
                    $header = $this->headerNames[$normalized];
                    $this->headers[$header] = \array_merge($this->headers[$header], $value);
                } else {
                    $this->headerNames[$normalized] = $header;
                    $this->headers[$header] = $value;
                }
            }
        }
        private function validateAndTrimHeader($header, $values): array
        {
            if (!\is_string($header) || 1 !== \preg_match("@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@D", $header)) {
                throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
            }
            if (!\is_array($values)) {
                // This is simple, just one value.
                if ((!\is_numeric($values) && !\is_string($values)) || 1 !== \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string) $values)) {
                    throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
                }
                return [\trim((string) $values, " \t")];
            }
            if (empty($values)) {
                throw new \InvalidArgumentException('Header values must be a string or an array of strings, empty array given');
            }
            // Assert Non empty array
            $returnValues = [];
            foreach ($values as $v) {
                if ((!\is_numeric($v) && !\is_string($v)) || 1 !== \preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@D", (string) $v)) {
                    throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
                }
                $returnValues[] = \trim((string) $v, " \t");
            }
            return $returnValues;
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\{RequestInterface, StreamInterface, UriInterface};
    class Request implements RequestInterface
    {
        use MessageTrait;
        use RequestTrait;
        public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
        {
            if (!($uri instanceof UriInterface)) {
                $uri = new Uri($uri);
            }
            $this->method = $method;
            $this->uri = $uri;
            $this->setHeaders($headers);
            $this->protocol = $version;
            if (!$this->hasHeader('Host')) {
                $this->updateHostFromUri();
            }
            // If we got no body, defer initialization of the stream until Request::getBody()
            if ('' !== $body && null !== $body) {
                $this->stream = Stream::create($body);
            }
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\UriInterface;
    trait RequestTrait
    {
        private $method;
        private $requestTarget;
        private $uri;
        public function getRequestTarget(): string
        {
            if (null !== $this->requestTarget) {
                return $this->requestTarget;
            }
            if ('' === $target = $this->uri->getPath()) {
                $target = '/';
            }
            if ('' !== $this->uri->getQuery()) {
                $target .= '?' . $this->uri->getQuery();
            }
            return $target;
        }
        public function withRequestTarget($requestTarget): RequestInterface
        {
            if (!\is_string($requestTarget)) {
                throw new \InvalidArgumentException('Request target must be a string');
            }
            if (\preg_match('#\s#', $requestTarget)) {
                throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
            }
            $new = clone $this;
            $new->requestTarget = $requestTarget;
            return $new;
        }
        public function getMethod(): string
        {
            return $this->method;
        }
        public function withMethod($method): RequestInterface
        {
            if (!\is_string($method)) {
                throw new \InvalidArgumentException('Method must be a string');
            }
            $new = clone $this;
            $new->method = $method;
            return $new;
        }
        public function getUri(): UriInterface
        {
            return $this->uri;
        }
        public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
        {
            if ($uri === $this->uri) {
                return $this;
            }
            $new = clone $this;
            $new->uri = $uri;
            if (!$preserveHost || !$this->hasHeader('Host')) {
                $new->updateHostFromUri();
            }
            return $new;
        }
        private function updateHostFromUri(): void
        {
            if ('' === $host = $this->uri->getHost()) {
                return;
            }
            if (null !== ($port = $this->uri->getPort())) {
                $host .= ':' . $port;
            }
            if (isset($this->headerNames['host'])) {
                $header = $this->headerNames['host'];
            } else {
                $this->headerNames['host'] = $header = 'Host';
            }
            // Ensure Host is the first header.
            // See: http://tools.ietf.org/html/rfc7230#section-5.4
            $this->headers = [$header => [$host]] + $this->headers;
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\{ResponseInterface, StreamInterface};
    class Response implements ResponseInterface
    {
        use MessageTrait;
        private const PHRASES = [
            100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing',
            200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 207 => 'Multi-status', 208 => 'Already Reported',
            300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 306 => 'Switch Proxy', 307 => 'Temporary Redirect',
            400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Time-out', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Large', 415 => 'Unsupported Media Type', 416 => 'Requested range not satisfiable', 417 => 'Expectation Failed', 418 => 'I\'m a teapot', 422 => 'Unprocessable Entity', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Unordered Collection', 426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests', 431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Time-out', 505 => 'HTTP Version not supported', 506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected', 511 => 'Network Authentication Required',
        ];
        private $reasonPhrase = '';
        private $statusCode;
        public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
        {
            // If we got no body, defer initialization of the stream until Response::getBody()
            if ('' !== $body && null !== $body) {
                $this->stream = Stream::create($body);
            }
            $this->statusCode = $status;
            $this->setHeaders($headers);
            if (null === $reason && isset(self::PHRASES[$this->statusCode])) {
                $this->reasonPhrase = self::PHRASES[$status];
            } else {
                $this->reasonPhrase = $reason ?? '';
            }
            $this->protocol = $version;
        }
        public function getStatusCode(): int
        {
            return $this->statusCode;
        }
        public function getReasonPhrase(): string
        {
            return $this->reasonPhrase;
        }
        public function withStatus($code, $reasonPhrase = ''): ResponseInterface
        {
            if (!\is_int($code) && !\is_string($code)) {
                throw new \InvalidArgumentException('Status code has to be an integer');
            }
            $code = (int) $code;
            if ($code < 100 || $code > 599) {
                throw new \InvalidArgumentException(\sprintf('Status code has to be an integer between 100 and 599. A status code of %d was given', $code));
            }
            $new = clone $this;
            $new->statusCode = $code;
            if ((null === $reasonPhrase || '' === $reasonPhrase) && isset(self::PHRASES[$new->statusCode])) {
                $reasonPhrase = self::PHRASES[$new->statusCode];
            }
            $new->reasonPhrase = $reasonPhrase;
            return $new;
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\{ServerRequestInterface, StreamInterface, UploadedFileInterface, UriInterface};
    class ServerRequest implements ServerRequestInterface
    {
        use MessageTrait;
        use RequestTrait;
        private $attributes = [];
        private $cookieParams = [];
        private $parsedBody;
        private $queryParams = [];
        private $serverParams;
        private $uploadedFiles = [];
        public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])
        {
            $this->serverParams = $serverParams;
            if (!($uri instanceof UriInterface)) {
                $uri = new Uri($uri);
            }
            $this->method = $method;
            $this->uri = $uri;
            $this->setHeaders($headers);
            $this->protocol = $version;
            \parse_str($uri->getQuery(), $this->queryParams);
            if (!$this->hasHeader('Host')) {
                $this->updateHostFromUri();
            }
            // If we got no body, defer initialization of the stream until ServerRequest::getBody()
            if ('' !== $body && null !== $body) {
                $this->stream = Stream::create($body);
            }
        }
        public function getServerParams(): array
        {
            return $this->serverParams;
        }
        public function getUploadedFiles(): array
        {
            return $this->uploadedFiles;
        }
        public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
        {
            $new = clone $this;
            $new->uploadedFiles = $uploadedFiles;
            return $new;
        }
        public function getCookieParams(): array
        {
            return $this->cookieParams;
        }
        public function withCookieParams(array $cookies): ServerRequestInterface
        {
            $new = clone $this;
            $new->cookieParams = $cookies;
            return $new;
        }
        public function getQueryParams(): array
        {
            return $this->queryParams;
        }
        public function withQueryParams(array $query): ServerRequestInterface
        {
            $new = clone $this;
            $new->queryParams = $query;
            return $new;
        }
        public function getParsedBody()
        {
            return $this->parsedBody;
        }
        public function withParsedBody($data): ServerRequestInterface
        {
            if (!\is_array($data) && !\is_object($data) && null !== $data) {
                throw new \InvalidArgumentException('First parameter to withParsedBody MUST be object, array or null');
            }
            $new = clone $this;
            $new->parsedBody = $data;
            return $new;
        }
        public function getAttributes(): array
        {
            return $this->attributes;
        }
        public function getAttribute($attribute, $default = null)
        {
            if (!\is_string($attribute)) {
                throw new \InvalidArgumentException('Attribute name must be a string');
            }
            if (false === \array_key_exists($attribute, $this->attributes)) {
                return $default;
            }
            return $this->attributes[$attribute];
        }
        public function withAttribute($attribute, $value): ServerRequestInterface
        {
            if (!\is_string($attribute)) {
                throw new \InvalidArgumentException('Attribute name must be a string');
            }
            $new = clone $this;
            $new->attributes[$attribute] = $value;
            return $new;
        }
        public function withoutAttribute($attribute): ServerRequestInterface
        {
            if (!\is_string($attribute)) {
                throw new \InvalidArgumentException('Attribute name must be a string');
            }
            if (false === \array_key_exists($attribute, $this->attributes)) {
                return $this;
            }
            $new = clone $this;
            unset($new->attributes[$attribute]);
            return $new;
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\StreamInterface;
    class Stream implements StreamInterface
    {
        function __toString():string { if ($this->isSeekable()) { $this->seek(0); } return $this->getContents(); }
        private $stream;
        private $seekable;
        private $readable;
        private $writable;
        private $uri;
        private $size;
        private const READ_WRITE_HASH = [
            'read' => [
                'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
                'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
                'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
                'x+t' => true, 'c+t' => true, 'a+' => true,
            ],
            'write' => [
                'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
                'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
                'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
                'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true,
            ],
        ];
        public function __construct($body)
        {
            if (!\is_resource($body)) {
                throw new \InvalidArgumentException('First argument to Stream::__construct() must be resource');
            }
            $this->stream = $body;
            $meta = \stream_get_meta_data($this->stream);
            $this->seekable = $meta['seekable'] && 0 === \fseek($this->stream, 0, \SEEK_CUR);
            $this->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
            $this->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
        }
        public static function create($body = ''): StreamInterface
        {
            if ($body instanceof StreamInterface) {
                return $body;
            }
            if (\is_string($body)) {
                if (200000 <= \strlen($body)) {
                    $body = self::openZvalStream($body);
                } else {
                    $resource = \fopen('php://memory', 'r+');
                    \fwrite($resource, $body);
                    \fseek($resource, 0);
                    $body = $resource;
                }
            }
            if (!\is_resource($body)) {
                throw new \InvalidArgumentException('First argument to Stream::create() must be a string, resource or StreamInterface');
            }
            return new self($body);
        }
        public function __destruct()
        {
            $this->close();
        }
        public function close(): void
        {
            if (isset($this->stream)) {
                if (\is_resource($this->stream)) {
                    \fclose($this->stream);
                }
                $this->detach();
            }
        }
        public function detach()
        {
            if (!isset($this->stream)) {
                return null;
            }
            $result = $this->stream;
            unset($this->stream);
            $this->size = $this->uri = null;
            $this->readable = $this->writable = $this->seekable = false;
            return $result;
        }
        private function getUri()
        {
            if (false !== $this->uri) {
                $this->uri = $this->getMetadata('uri') ?? false;
            }
            return $this->uri;
        }
        public function getSize(): ?int
        {
            if (null !== $this->size) {
                return $this->size;
            }
            if (!isset($this->stream)) {
                return null;
            }
            // Clear the stat cache if the stream has a URI
            if ($uri = $this->getUri()) {
                \clearstatcache(true, $uri);
            }
            $stats = \fstat($this->stream);
            if (isset($stats['size'])) {
                $this->size = $stats['size'];
                return $this->size;
            }
            return null;
        }
        public function tell(): int
        {
            if (!isset($this->stream)) {
                throw new \RuntimeException('Stream is detached');
            }
            if (false === $result = @\ftell($this->stream)) {
                throw new \RuntimeException('Unable to determine stream position: ' . (\error_get_last()['message'] ?? ''));
            }
            return $result;
        }
        public function eof(): bool
        {
            return !isset($this->stream) || \feof($this->stream);
        }
        public function isSeekable(): bool
        {
            return $this->seekable;
        }
        public function seek($offset, $whence = \SEEK_SET): void
        {
            if (!isset($this->stream)) {
                throw new \RuntimeException('Stream is detached');
            }
            if (!$this->seekable) {
                throw new \RuntimeException('Stream is not seekable');
            }
            if (-1 === \fseek($this->stream, $offset, $whence)) {
                throw new \RuntimeException('Unable to seek to stream position "' . $offset . '" with whence ' . \var_export($whence, true));
            }
        }
        public function rewind(): void
        {
            $this->seek(0);
        }
        public function isWritable(): bool
        {
            return $this->writable;
        }
        public function write($string): int
        {
            if (!isset($this->stream)) {
                throw new \RuntimeException('Stream is detached');
            }
            if (!$this->writable) {
                throw new \RuntimeException('Cannot write to a non-writable stream');
            }
            // We can't know the size after writing anything
            $this->size = null;
            if (false === $result = @\fwrite($this->stream, $string)) {
                throw new \RuntimeException('Unable to write to stream: ' . (\error_get_last()['message'] ?? ''));
            }
            return $result;
        }
        public function isReadable(): bool
        {
            return $this->readable;
        }
        public function read($length): string
        {
            if (!isset($this->stream)) {
                throw new \RuntimeException('Stream is detached');
            }
            if (!$this->readable) {
                throw new \RuntimeException('Cannot read from non-readable stream');
            }
            if (false === $result = @\fread($this->stream, $length)) {
                throw new \RuntimeException('Unable to read from stream: ' . (\error_get_last()['message'] ?? ''));
            }
            return $result;
        }
        public function getContents(): string
        {
            if (!isset($this->stream)) {
                throw new \RuntimeException('Stream is detached');
            }
            $exception = null;
            \set_error_handler(static function ($type, $message) use (&$exception) {
                throw $exception = new \RuntimeException('Unable to read stream contents: ' . $message);
            });
            try {
                return \stream_get_contents($this->stream);
            } catch (\Throwable $e) {
                throw $e === $exception ? $e : new \RuntimeException('Unable to read stream contents: ' . $e->getMessage(), 0, $e);
            } finally {
                \restore_error_handler();
            }
        }
        public function getMetadata($key = null)
        {
            if (null !== $key && !\is_string($key)) {
                throw new \InvalidArgumentException('Metadata key must be a string');
            }
            if (!isset($this->stream)) {
                return $key ? null : [];
            }
            $meta = \stream_get_meta_data($this->stream);
            if (null === $key) {
                return $meta;
            }
            return $meta[$key] ?? null;
        }
        private static function openZvalStream(string $body)
        {
            static $wrapper;
            $wrapper ?? \stream_wrapper_register('Nyholm-Psr7-Zval', $wrapper = \get_class(new class() {
                public $context;
                private $data;
                private $position = 0;
                public function stream_open(): bool
                {
                    $this->data = \stream_context_get_options($this->context)['Nyholm-Psr7-Zval']['data'];
                    \stream_context_set_option($this->context, 'Nyholm-Psr7-Zval', 'data', null);
                    return true;
                }
                public function stream_read(int $count): string
                {
                    $result = \substr($this->data, $this->position, $count);
                    $this->position += \strlen($result);
                    return $result;
                }
                public function stream_write(string $data): int
                {
                    $this->data = \substr_replace($this->data, $data, $this->position, \strlen($data));
                    $this->position += \strlen($data);
                    return \strlen($data);
                }
                public function stream_tell(): int
                {
                    return $this->position;
                }
                public function stream_eof(): bool
                {
                    return \strlen($this->data) <= $this->position;
                }
                public function stream_stat(): array
                {
                    return [
                        'mode' => 33206, // POSIX_S_IFREG | 0666
                        'nlink' => 1,
                        'rdev' => -1,
                        'size' => \strlen($this->data),
                        'blksize' => -1,
                        'blocks' => -1,
                    ];
                }
                public function stream_seek(int $offset, int $whence): bool
                {
                    if (\SEEK_SET === $whence && (0 <= $offset && \strlen($this->data) >= $offset)) {
                        $this->position = $offset;
                    } elseif (\SEEK_CUR === $whence && 0 <= $offset) {
                        $this->position += $offset;
                    } elseif (\SEEK_END === $whence && (0 > $offset && 0 <= $offset = \strlen($this->data) + $offset)) {
                        $this->position = $offset;
                    } else {
                        return false;
                    }
                    return true;
                }
                public function stream_set_option(): bool
                {
                    return true;
                }
                public function stream_truncate(int $new_size): bool
                {
                    if ($new_size) {
                        $this->data = \substr($this->data, 0, $new_size);
                        $this->position = \min($this->position, $new_size);
                    } else {
                        $this->data = '';
                        $this->position = 0;
                    }
                    return true;
                }
            }));
            $context = \stream_context_create(['Nyholm-Psr7-Zval' => ['data' => $body]]);
            if (!$stream = @\fopen('Nyholm-Psr7-Zval://', 'r+', false, $context)) {
                \stream_wrapper_register('Nyholm-Psr7-Zval', $wrapper);
                $stream = \fopen('Nyholm-Psr7-Zval://', 'r+', false, $context);
            }
            return $stream;
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\StreamInterface;
    use Symfony\Component\Debug\ErrorHandler as SymfonyLegacyErrorHandler;
    use Symfony\Component\ErrorHandler\ErrorHandler as SymfonyErrorHandler;
    if (\PHP_VERSION_ID >= 70400 || (new \ReflectionMethod(StreamInterface::class, '__toString'))->hasReturnType()) {
        trait StreamTrait
        {
            public function __toString(): string
            {
                if ($this->isSeekable()) {
                    $this->seek(0);
                }
                return $this->getContents();
            }
        }
    } else {
        trait StreamTrait
        {
            public function __toString()
            {
                try {
                    if ($this->isSeekable()) {
                        $this->seek(0);
                    }
                    return $this->getContents();
                } catch (\Throwable $e) {
                    if (\is_array($errorHandler = \set_error_handler('var_dump'))) {
                        $errorHandler = $errorHandler[0] ?? null;
                    }
                    \restore_error_handler();
                    if ($e instanceof \Error || $errorHandler instanceof SymfonyErrorHandler || $errorHandler instanceof SymfonyLegacyErrorHandler) {
                        return \trigger_error((string) $e, \E_USER_ERROR);
                    }
                    return '';
                }
            }
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\{StreamInterface, UploadedFileInterface};
    class UploadedFile implements UploadedFileInterface
    {
        private const ERRORS = [
            \UPLOAD_ERR_OK => 1,
            \UPLOAD_ERR_INI_SIZE => 1,
            \UPLOAD_ERR_FORM_SIZE => 1,
            \UPLOAD_ERR_PARTIAL => 1,
            \UPLOAD_ERR_NO_FILE => 1,
            \UPLOAD_ERR_NO_TMP_DIR => 1,
            \UPLOAD_ERR_CANT_WRITE => 1,
            \UPLOAD_ERR_EXTENSION => 1,
        ];
        private $clientFilename;
        private $clientMediaType;
        private $error;
        private $file;
        private $moved = false;
        private $size;
        private $stream;
        public function __construct($streamOrFile, $size, $errorStatus, $clientFilename = null, $clientMediaType = null)
        {
            if (false === \is_int($errorStatus) || !isset(self::ERRORS[$errorStatus])) {
                throw new \InvalidArgumentException('Upload file error status must be an integer value and one of the "UPLOAD_ERR_*" constants');
            }
            if (false === \is_int($size)) {
                throw new \InvalidArgumentException('Upload file size must be an integer');
            }
            if (null !== $clientFilename && !\is_string($clientFilename)) {
                throw new \InvalidArgumentException('Upload file client filename must be a string or null');
            }
            if (null !== $clientMediaType && !\is_string($clientMediaType)) {
                throw new \InvalidArgumentException('Upload file client media type must be a string or null');
            }
            $this->error = $errorStatus;
            $this->size = $size;
            $this->clientFilename = $clientFilename;
            $this->clientMediaType = $clientMediaType;
            if (\UPLOAD_ERR_OK === $this->error) {
                // Depending on the value set file or stream variable.
                if (\is_string($streamOrFile) && '' !== $streamOrFile) {
                    $this->file = $streamOrFile;
                } elseif (\is_resource($streamOrFile)) {
                    $this->stream = Stream::create($streamOrFile);
                } elseif ($streamOrFile instanceof StreamInterface) {
                    $this->stream = $streamOrFile;
                } else {
                    throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
                }
            }
        }
        private function validateActive(): void
        {
            if (\UPLOAD_ERR_OK !== $this->error) {
                throw new \RuntimeException('Cannot retrieve stream due to upload error');
            }
            if ($this->moved) {
                throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
            }
        }
        public function getStream(): StreamInterface
        {
            $this->validateActive();
            if ($this->stream instanceof StreamInterface) {
                return $this->stream;
            }
            if (false === $resource = @\fopen($this->file, 'r')) {
                throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $this->file, \error_get_last()['message'] ?? ''));
            }
            return Stream::create($resource);
        }
        public function moveTo($targetPath): void
        {
            $this->validateActive();
            if (!\is_string($targetPath) || '' === $targetPath) {
                throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
            }
            if (null !== $this->file) {
                $this->moved = 'cli' === \PHP_SAPI ? @\rename($this->file, $targetPath) : @\move_uploaded_file($this->file, $targetPath);
                if (false === $this->moved) {
                    throw new \RuntimeException(\sprintf('Uploaded file could not be moved to "%s": %s', $targetPath, \error_get_last()['message'] ?? ''));
                }
            } else {
                $stream = $this->getStream();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                if (false === $resource = @\fopen($targetPath, 'w')) {
                    throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $targetPath, \error_get_last()['message'] ?? ''));
                }
                $dest = Stream::create($resource);
                while (!$stream->eof()) {
                    if (!$dest->write($stream->read(1048576))) {
                        break;
                    }
                }
                $this->moved = true;
            }
        }
        public function getSize(): int
        {
            return $this->size;
        }
        public function getError(): int
        {
            return $this->error;
        }
        public function getClientFilename(): ?string
        {
            return $this->clientFilename;
        }
        public function getClientMediaType(): ?string
        {
            return $this->clientMediaType;
        }
    }
}
namespace Nyholm\Psr7 {
    use Psr\Http\Message\UriInterface;
    class Uri implements UriInterface
    {
        private const SCHEMES = ['http' => 80, 'https' => 443];
        private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
        private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
        private const CHAR_GEN_DELIMS = ':\/\?#\[\]@';
        private $scheme = '';
        private $userInfo = '';
        private $host = '';
        private $port;
        private $path = '';
        private $query = '';
        private $fragment = '';
        public function __construct(string $uri = '')
        {
            if ('' !== $uri) {
                if (false === $parts = \parse_url($uri)) {
                    throw new \InvalidArgumentException(\sprintf('Unable to parse URI: "%s"', $uri));
                }
                // Apply parse_url parts to a URI.
                $this->scheme = isset($parts['scheme']) ? \strtr($parts['scheme'], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') : '';
                $this->userInfo = $parts['user'] ?? '';
                $this->host = isset($parts['host']) ? \strtr($parts['host'], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') : '';
                $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
                $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
                $this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
                $this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';
                if (isset($parts['pass'])) {
                    $this->userInfo .= ':' . $parts['pass'];
                }
            }
        }
        public function __toString(): string
        {
            return self::createUriString($this->scheme, $this->getAuthority(), $this->path, $this->query, $this->fragment);
        }
        public function getScheme(): string
        {
            return $this->scheme;
        }
        public function getAuthority(): string
        {
            if ('' === $this->host) {
                return '';
            }
            $authority = $this->host;
            if ('' !== $this->userInfo) {
                $authority = $this->userInfo . '@' . $authority;
            }
            if (null !== $this->port) {
                $authority .= ':' . $this->port;
            }
            return $authority;
        }
        public function getUserInfo(): string
        {
            return $this->userInfo;
        }
        public function getHost(): string
        {
            return $this->host;
        }
        public function getPort(): ?int
        {
            return $this->port;
        }
        public function getPath(): string
        {
            $path = $this->path;
            if ('' !== $path && '/' !== $path[0]) {
                if ('' !== $this->host) {
                    // If the path is rootless and an authority is present, the path MUST be prefixed by "/"
                    $path = '/' . $path;
                }
            } elseif (isset($path[1]) && '/' === $path[1]) {
                // If the path is starting with more than one "/", the
                // starting slashes MUST be reduced to one.
                $path = '/' . \ltrim($path, '/');
            }
            return $path;
        }
        public function getQuery(): string
        {
            return $this->query;
        }
        public function getFragment(): string
        {
            return $this->fragment;
        }
        public function withScheme($scheme): UriInterface
        {
            if (!\is_string($scheme)) {
                throw new \InvalidArgumentException('Scheme must be a string');
            }
            if ($this->scheme === $scheme = \strtr($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')) {
                return $this;
            }
            $new = clone $this;
            $new->scheme = $scheme;
            $new->port = $new->filterPort($new->port);
            return $new;
        }
        public function withUserInfo($user, $password = null): UriInterface
        {
            if (!\is_string($user)) {
                throw new \InvalidArgumentException('User must be a string');
            }
            $info = \preg_replace_callback('/[' . self::CHAR_GEN_DELIMS . self::CHAR_SUB_DELIMS . ']++/', [__CLASS__, 'rawurlencodeMatchZero'], $user);
            if (null !== $password && '' !== $password) {
                if (!\is_string($password)) {
                    throw new \InvalidArgumentException('Password must be a string');
                }
                $info .= ':' . \preg_replace_callback('/[' . self::CHAR_GEN_DELIMS . self::CHAR_SUB_DELIMS . ']++/', [__CLASS__, 'rawurlencodeMatchZero'], $password);
            }
            if ($this->userInfo === $info) {
                return $this;
            }
            $new = clone $this;
            $new->userInfo = $info;
            return $new;
        }
        public function withHost($host): UriInterface
        {
            if (!\is_string($host)) {
                throw new \InvalidArgumentException('Host must be a string');
            }
            if ($this->host === $host = \strtr($host, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')) {
                return $this;
            }
            $new = clone $this;
            $new->host = $host;
            return $new;
        }
        public function withPort($port): UriInterface
        {
            if ($this->port === $port = $this->filterPort($port)) {
                return $this;
            }
            $new = clone $this;
            $new->port = $port;
            return $new;
        }
        public function withPath($path): UriInterface
        {
            if ($this->path === $path = $this->filterPath($path)) {
                return $this;
            }
            $new = clone $this;
            $new->path = $path;
            return $new;
        }
        public function withQuery($query): UriInterface
        {
            if ($this->query === $query = $this->filterQueryAndFragment($query)) {
                return $this;
            }
            $new = clone $this;
            $new->query = $query;
            return $new;
        }
        public function withFragment($fragment): UriInterface
        {
            if ($this->fragment === $fragment = $this->filterQueryAndFragment($fragment)) {
                return $this;
            }
            $new = clone $this;
            $new->fragment = $fragment;
            return $new;
        }
        private static function createUriString(string $scheme, string $authority, string $path, string $query, string $fragment): string
        {
            $uri = '';
            if ('' !== $scheme) {
                $uri .= $scheme . ':';
            }
            if ('' !== $authority) {
                $uri .= '//' . $authority;
            }
            if ('' !== $path) {
                if ('/' !== $path[0]) {
                    if ('' !== $authority) {
                        // If the path is rootless and an authority is present, the path MUST be prefixed by "/"
                        $path = '/' . $path;
                    }
                } elseif (isset($path[1]) && '/' === $path[1]) {
                    if ('' === $authority) {
                        // If the path is starting with more than one "/" and no authority is present, the
                        // starting slashes MUST be reduced to one.
                        $path = '/' . \ltrim($path, '/');
                    }
                }
                $uri .= $path;
            }
            if ('' !== $query) {
                $uri .= '?' . $query;
            }
            if ('' !== $fragment) {
                $uri .= '#' . $fragment;
            }
            return $uri;
        }
        private static function isNonStandardPort(string $scheme, int $port): bool
        {
            return !isset(self::SCHEMES[$scheme]) || $port !== self::SCHEMES[$scheme];
        }
        private function filterPort($port): ?int
        {
            if (null === $port) {
                return null;
            }
            $port = (int) $port;
            if (0 > $port || 0xFFFF < $port) {
                throw new \InvalidArgumentException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
            }
            return self::isNonStandardPort($this->scheme, $port) ? $port : null;
        }
        private function filterPath($path): string
        {
            if (!\is_string($path)) {
                throw new \InvalidArgumentException('Path must be a string');
            }
            return \preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/', [__CLASS__, 'rawurlencodeMatchZero'], $path);
        }
        private function filterQueryAndFragment($str): string
        {
            if (!\is_string($str)) {
                throw new \InvalidArgumentException('Query and fragment must be a string');
            }
            return \preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/', [__CLASS__, 'rawurlencodeMatchZero'], $str);
        }
        private static function rawurlencodeMatchZero(array $match): string
        {
            return \rawurlencode($match[0]);
        }
    }
}
namespace Nyholm\Psr7Server {
    use Psr\Http\Message\ServerRequestFactoryInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Message\StreamFactoryInterface;
    use Psr\Http\Message\StreamInterface;
    use Psr\Http\Message\UploadedFileFactoryInterface;
    use Psr\Http\Message\UploadedFileInterface;
    use Psr\Http\Message\UriFactoryInterface;
    use Psr\Http\Message\UriInterface;
    final class ServerRequestCreator implements ServerRequestCreatorInterface
    {
        private $serverRequestFactory;
        private $uriFactory;
        private $uploadedFileFactory;
        private $streamFactory;
        public function __construct(
            ServerRequestFactoryInterface $serverRequestFactory,
            UriFactoryInterface $uriFactory,
            UploadedFileFactoryInterface $uploadedFileFactory,
            StreamFactoryInterface $streamFactory
        ) {
            $this->serverRequestFactory = $serverRequestFactory;
            $this->uriFactory = $uriFactory;
            $this->uploadedFileFactory = $uploadedFileFactory;
            $this->streamFactory = $streamFactory;
        }
        public function fromGlobals(): ServerRequestInterface
        {
            $server = $_SERVER;
            if (false === isset($server['REQUEST_METHOD'])) {
                $server['REQUEST_METHOD'] = 'GET';
            }
            $headers = \function_exists('getallheaders') ? getallheaders() : static::getHeadersFromServer($_SERVER);
            $post = null;
            if ('POST' === $this->getMethodFromEnv($server)) {
                foreach ($headers as $headerName => $headerValue) {
                    if (true === \is_int($headerName) || 'content-type' !== \strtolower($headerName)) {
                        continue;
                    }
                    if (\in_array(
                        \strtolower(\trim(\explode(';', $headerValue, 2)[0])),
                        ['application/x-www-form-urlencoded', 'multipart/form-data']
                    )) {
                        $post = $_POST;
                        break;
                    }
                }
            }
            return $this->fromArrays($server, $headers, $_COOKIE, $_GET, $post, $_FILES, \fopen('php://input', 'r') ?: null);
        }
        public function fromArrays(array $server, array $headers = [], array $cookie = [], array $get = [], ?array $post = null, array $files = [], $body = null): ServerRequestInterface
        {
            $method = $this->getMethodFromEnv($server);
            $uri = $this->getUriFromEnvWithHTTP($server);
            $protocol = isset($server['SERVER_PROTOCOL']) ? \str_replace('HTTP/', '', $server['SERVER_PROTOCOL']) : '1.1';
            $serverRequest = $this->serverRequestFactory->createServerRequest($method, $uri, $server);
            foreach ($headers as $name => $value) {
                // Because PHP automatically casts array keys set with numeric strings to integers, we have to make sure
                // that numeric headers will not be sent along as integers, as withAddedHeader can only accept strings.
                if (\is_int($name)) {
                    $name = (string) $name;
                }
                $serverRequest = $serverRequest->withAddedHeader($name, $value);
            }
            $serverRequest = $serverRequest
                ->withProtocolVersion($protocol)
                ->withCookieParams($cookie)
                ->withQueryParams($get)
                ->withParsedBody($post)
                ->withUploadedFiles($this->normalizeFiles($files));
            if (null === $body) {
                return $serverRequest;
            }
            if (\is_resource($body)) {
                $body = $this->streamFactory->createStreamFromResource($body);
            } elseif (\is_string($body)) {
                $body = $this->streamFactory->createStream($body);
            } elseif (!$body instanceof StreamInterface) {
                throw new \InvalidArgumentException('The $body parameter to ServerRequestCreator::fromArrays must be string, resource or StreamInterface');
            }
            return $serverRequest->withBody($body);
        }
        public static function getHeadersFromServer(array $server): array
        {
            $headers = [];
            foreach ($server as $key => $value) {
                // Apache prefixes environment variables with REDIRECT_
                // if they are added by rewrite rules
                if (0 === \strpos($key, 'REDIRECT_')) {
                    $key = \substr($key, 9);
                    // We will not overwrite existing variables with the
                    // prefixed versions, though
                    if (\array_key_exists($key, $server)) {
                        continue;
                    }
                }
                if ($value && 0 === \strpos($key, 'HTTP_')) {
                    $name = \strtr(\strtolower(\substr($key, 5)), '_', '-');
                    $headers[$name] = $value;
                    continue;
                }
                if ($value && 0 === \strpos($key, 'CONTENT_')) {
                    $name = 'content-'.\strtolower(\substr($key, 8));
                    $headers[$name] = $value;
                    continue;
                }
            }
            return $headers;
        }
        private function getMethodFromEnv(array $environment): string
        {
            if (false === isset($environment['REQUEST_METHOD'])) {
                throw new \InvalidArgumentException('Cannot determine HTTP method');
            }
            return $environment['REQUEST_METHOD'];
        }
        private function getUriFromEnvWithHTTP(array $environment): UriInterface
        {
            $uri = $this->createUriFromArray($environment);
            if (empty($uri->getScheme())) {
                $uri = $uri->withScheme('http');
            }
            return $uri;
        }
        private function normalizeFiles(array $files): array
        {
            $normalized = [];
            foreach ($files as $key => $value) {
                if ($value instanceof UploadedFileInterface) {
                    $normalized[$key] = $value;
                } elseif (\is_array($value) && isset($value['tmp_name'])) {
                    $normalized[$key] = $this->createUploadedFileFromSpec($value);
                } elseif (\is_array($value)) {
                    $normalized[$key] = $this->normalizeFiles($value);
                } else {
                    throw new \InvalidArgumentException('Invalid value in files specification');
                }
            }
            return $normalized;
        }
        private function createUploadedFileFromSpec(array $value)
        {
            if (\is_array($value['tmp_name'])) {
                return $this->normalizeNestedFileSpec($value);
            }
            if (UPLOAD_ERR_OK !== $value['error']) {
                $stream = $this->streamFactory->createStream();
            } else {
                try {
                    $stream = $this->streamFactory->createStreamFromFile($value['tmp_name']);
                } catch (\RuntimeException $e) {
                    $stream = $this->streamFactory->createStream();
                }
            }
            return $this->uploadedFileFactory->createUploadedFile(
                $stream,
                (int) $value['size'],
                (int) $value['error'],
                $value['name'],
                $value['type']
            );
        }
        private function normalizeNestedFileSpec(array $files = []): array
        {
            $normalizedFiles = [];
            foreach (\array_keys($files['tmp_name']) as $key) {
                $spec = [
                    'tmp_name' => $files['tmp_name'][$key],
                    'size' => $files['size'][$key],
                    'error' => $files['error'][$key],
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                ];
                $normalizedFiles[$key] = $this->createUploadedFileFromSpec($spec);
            }
            return $normalizedFiles;
        }
        private function createUriFromArray(array $server): UriInterface
        {
            $uri = $this->uriFactory->createUri('');
            if (isset($server['HTTP_X_FORWARDED_PROTO'])) {
                $uri = $uri->withScheme($server['HTTP_X_FORWARDED_PROTO']);
            } else {
                if (isset($server['REQUEST_SCHEME'])) {
                    $uri = $uri->withScheme($server['REQUEST_SCHEME']);
                } elseif (isset($server['HTTPS'])) {
                    $uri = $uri->withScheme('on' === $server['HTTPS'] ? 'https' : 'http');
                }
                if (isset($server['SERVER_PORT'])) {
                    $uri = $uri->withPort($server['SERVER_PORT']);
                }
            }
            if (isset($server['HTTP_HOST'])) {
                if (1 === \preg_match('/^(.+)\:(\d+)$/', $server['HTTP_HOST'], $matches)) {
                    $uri = $uri->withHost($matches[1])->withPort($matches[2]);
                } else {
                    $uri = $uri->withHost($server['HTTP_HOST']);
                }
            } elseif (isset($server['SERVER_NAME'])) {
                $uri = $uri->withHost($server['SERVER_NAME']);
            }
            if (isset($server['REQUEST_URI'])) {
                $uri = $uri->withPath(\current(\explode('?', $server['REQUEST_URI'])));
            }
            if (isset($server['QUERY_STRING'])) {
                $uri = $uri->withQuery($server['QUERY_STRING']);
            }
            return $uri;
        }
    }
}
namespace Nyholm\Psr7Server {
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Message\StreamInterface;
    interface ServerRequestCreatorInterface
    {
        public function fromGlobals(): ServerRequestInterface;
        public function fromArrays(
            array $server,
            array $headers = [],
            array $cookie = [],
            array $get = [],
            ?array $post = null,
            array $files = [],
            $body = null
        ): ServerRequestInterface;
        public static function getHeadersFromServer(array $server): array;
    }
}
namespace afcodehub\gatewaypro\Cache\Base {
    use afcodehub\gatewaypro\Cache\Cache;
    class BaseCache implements Cache
    {
        public function __construct()
        {
        }
        public function set(string $key, string $value, int $ttl = 0): bool
        {
            return true;
        }
        public function get(string $key): string
        {
            return '';
        }
        public function clear(): bool
        {
            return true;
        }
        public function ping(): int
        {
            $start = microtime(true);
            $this->get('__ping__');
            return intval((microtime(true)-$start)*1000000);
        }
    }
}
namespace afcodehub\gatewaypro\Cache {
    interface Cache
    {
        public function set(string $key, string $value, int $ttl = 0): bool;
        public function get(string $key): string;
        public function clear(): bool;
        public function ping(): int;
    }
}
namespace afcodehub\gatewaypro\Cache {
    class CacheFactory
    {
        public static function create(string $type, string $prefix, string $config): Cache
        {
            switch ($type) {
                case 'TempFile':
                    $cache = new TempFileCache($prefix, $config);
                    break;
                case 'Redis':
                    $cache = new RedisCache($prefix, $config);
                    break;
                case 'Memcache':
                    $cache = new MemcacheCache($prefix, $config);
                    break;
                case 'Memcached':
                    $cache = new MemcachedCache($prefix, $config);
                    break;
                default:
                    $cache = new NoCache();
            }
            return $cache;
        }
    }
}
namespace afcodehub\gatewaypro\Cache {
    use afcodehub\gatewaypro\Cache\Base\BaseCache;
    class MemcacheCache extends BaseCache implements Cache
    {
        protected $prefix;
        protected $memcache;
        public function __construct(string $prefix, string $config)
        {
            $this->prefix = $prefix;
            if ($config == '') {
                $address = 'localhost';
                $port = 11211;
            } elseif (strpos($config, ':') === false) {
                $address = $config;
                $port = 11211;
            } else {
                list($address, $port) = explode(':', $config);
            }
            $this->memcache = $this->create();
            $this->memcache->addServer($address, $port);
        }
        protected function create() /*: \Memcache*/
        {
            return new \Memcache();
        }
        public function set(string $key, string $value, int $ttl = 0): bool
        {
            return $this->memcache->set($this->prefix . $key, $value, 0, $ttl);
        }
        public function get(string $key): string
        {
            return $this->memcache->get($this->prefix . $key) ?: '';
        }
        public function clear(): bool
        {
            return $this->memcache->flush();
        }
    }
}
namespace afcodehub\gatewaypro\Cache {
    class MemcachedCache extends MemcacheCache
    {
        protected function create() /*: \Memcached*/
        {
            return new \Memcached();
        }
        public function set(string $key, string $value, int $ttl = 0): bool
        {
            return $this->memcache->set($this->prefix . $key, $value, $ttl);
        }
    }
}
namespace afcodehub\gatewaypro\Cache {
    use afcodehub\gatewaypro\Cache\Base\BaseCache;
    class NoCache extends BaseCache implements Cache
    {
    }
}
namespace afcodehub\gatewaypro\Cache {
    use afcodehub\gatewaypro\Cache\Base\BaseCache;
    class RedisCache extends BaseCache implements Cache
    {
        protected $prefix;
        protected $redis;
        public function __construct(string $prefix, string $config)
        {
            $this->prefix = $prefix;
            if ($config == '') {
                $config = '127.0.0.1';
            }
            $params = explode(':', $config, 6);
            if (isset($params[3])) {
                $params[3] = null;
            }
            $this->redis = new \Redis();
            call_user_func_array(array($this->redis, 'pconnect'), $params);
        }
        public function set(string $key, string $value, int $ttl = 0): bool
        {
            return $this->redis->set($this->prefix . $key, $value, $ttl);
        }
        public function get(string $key): string
        {
            return $this->redis->get($this->prefix . $key) ?: '';
        }
        public function clear(): bool
        {
            return $this->redis->flushDb();
        }
    }
}
namespace afcodehub\gatewaypro\Cache {
    use afcodehub\gatewaypro\Cache\Base\BaseCache;
    class TempFileCache extends BaseCache implements Cache
    {
        const SUFFIX = 'cache';
        private $path;
        private $segments;
        public function __construct(string $prefix, string $config)
        {
            $this->segments = [];
            $s = DIRECTORY_SEPARATOR;
            $ps = PATH_SEPARATOR;
            if ($config == '') {
                $this->path = sys_get_temp_dir() . $s . $prefix . self::SUFFIX;
            } elseif (strpos($config, $ps) === false) {
                $this->path = $config;
            } else {
                list($path, $segments) = explode($ps, $config);
                $this->path = $path;
                $this->segments = explode(',', $segments);
            }
            if (file_exists($this->path) && is_dir($this->path)) {
                $this->clean($this->path, array_filter($this->segments), strlen(md5('')), false);
            }
        }
        private function getFileName(string $key): string
        {
            $s = DIRECTORY_SEPARATOR;
            $md5 = md5($key);
            $filename = rtrim($this->path, $s) . $s;
            $i = 0;
            foreach ($this->segments as $segment) {
                $filename .= substr($md5, $i, $segment) . $s;
                $i += $segment;
            }
            $filename .= substr($md5, $i);
            return $filename;
        }
        public function set(string $key, string $value, int $ttl = 0): bool
        {
            $filename = $this->getFileName($key);
            $dirname = dirname($filename);
            if (!file_exists($dirname)) {
                if (!mkdir($dirname, 0755, true)) {
                    return false;
                }
            }
            $string = $ttl . '|' . $value;
            return $this->filePutContents($filename, $string) !== false;
        }
        private function filePutContents($filename, $string)
        {
            return file_put_contents($filename, $string, LOCK_EX);
        }
        private function fileGetContents($filename)
        {
            $file = fopen($filename, 'rb');
            if ($file === false) {
                return false;
            }
            $lock = flock($file, LOCK_SH);
            if (!$lock) {
                fclose($file);
                return false;
            }
            $string = '';
            while (!feof($file)) {
                $string .= fread($file, 8192);
            }
            flock($file, LOCK_UN);
            fclose($file);
            return $string;
        }
        private function getString($filename): string
        {
            $data = $this->fileGetContents($filename);
            if ($data === false) {
                return '';
            }
            if (strpos($data, '|') === false) {
                return '';
            }
            list($ttl, $string) = explode('|', $data, 2);
            if ($ttl > 0 && time() - filemtime($filename) > $ttl) {
                return '';
            }
            return $string;
        }
        public function get(string $key): string
        {
            $filename = $this->getFileName($key);
            if (!file_exists($filename)) {
                return '';
            }
            $string = $this->getString($filename);
            if ($string == null) {
                return '';
            }
            return $string;
        }
        private function clean(string $path, array $segments, int $len, bool $all) /*: void*/
        {
            $entries = scandir($path);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $filename = $path . DIRECTORY_SEPARATOR . $entry;
                if (count($segments) == 0) {
                    if (strlen($entry) != $len) {
                        continue;
                    }
                    if (file_exists($filename) && is_file($filename)) {
                        if ($all || $this->getString($filename) == null) {
                            @unlink($filename);
                        }
                    }
                } else {
                    if (strlen($entry) != $segments[0]) {
                        continue;
                    }
                    if (file_exists($filename) && is_dir($filename)) {
                        $this->clean($filename, array_slice($segments, 1), $len - $segments[0], $all);
                        @rmdir($filename);
                    }
                }
            }
        }
        public function clear(): bool
        {
            if (!file_exists($this->path) || !is_dir($this->path)) {
                return false;
            }
            $this->clean($this->path, array_filter($this->segments), strlen(md5('')), true);
            return true;
        }
    }
}
namespace afcodehub\gatewaypro\Column\Reflection {
    use afcodehub\gatewaypro\Database\GenericReflection;
    class ReflectedColumn implements \JsonSerializable
    {
        const DEFAULT_LENGTH = 255;
        const DEFAULT_PRECISION = 19;
        const DEFAULT_SCALE = 4;
        private $name;
        private $realName;
        private $type;
        private $length;
        private $precision;
        private $scale;
        private $nullable;
        private $pk;
        private $fk;
        public function __construct(string $name, string $realName, string $type, int $length, int $precision, int $scale, bool $nullable, bool $pk, string $fk)
        {
            $this->name = $name;
            $this->realName = $realName;
            $this->type = $type;
            $this->length = $length;
            $this->precision = $precision;
            $this->scale = $scale;
            $this->nullable = $nullable;
            $this->pk = $pk;
            $this->fk = $fk;
            $this->sanitize();
        }
        private static function parseColumnType(string $columnType, int &$length, int &$precision, int &$scale) /*: void*/
        {
            if (!$columnType) {
                return;
            }
            $pos = strpos($columnType, '(');
            if ($pos) {
                $dataSize = rtrim(substr($columnType, $pos + 1), ')');
                if ($length) {
                    $length = (int) $dataSize;
                } else {
                    $pos = strpos($dataSize, ',');
                    if ($pos) {
                        $precision = (int) substr($dataSize, 0, $pos);
                        $scale = (int) substr($dataSize, $pos + 1);
                    } else {
                        $precision = (int) $dataSize;
                        $scale = 0;
                    }
                }
            }
        }
        private static function getDataSize(int $length, int $precision, int $scale): string
        {
            $dataSize = '';
            if ($length) {
                $dataSize = $length;
            } elseif ($precision) {
                if ($scale) {
                    $dataSize = $precision . ',' . $scale;
                } else {
                    $dataSize = $precision;
                }
            }
            return $dataSize;
        }
        public static function fromReflection(GenericReflection $reflection, array $columnResult): ReflectedColumn
        {
            $name = $columnResult['COLUMN_NAME'];
            $realName = $columnResult['COLUMN_REAL_NAME'];
            $dataType = $columnResult['DATA_TYPE'];
            $length = (int) $columnResult['CHARACTER_MAXIMUM_LENGTH'];
            $precision = (int) $columnResult['NUMERIC_PRECISION'];
            $scale = (int) $columnResult['NUMERIC_SCALE'];
            $columnType = $columnResult['COLUMN_TYPE'];
            self::parseColumnType($columnType, $length, $precision, $scale);
            $dataSize = self::getDataSize($length, $precision, $scale);
            $type = $reflection->toJdbcType($dataType, $dataSize);
            $nullable = in_array(strtoupper($columnResult['IS_NULLABLE']), ['TRUE', 'YES', 'T', 'Y', '1']);
            $pk = false;
            $fk = '';
            return new ReflectedColumn($name, $realName, $type, $length, $precision, $scale, $nullable, $pk, $fk);
        }
        public static function fromJson( /* object */$json): ReflectedColumn
        {
            $name = $json->alias ?? $json->name;
            $realName = $json->name;
            $type = $json->type;
            $length = isset($json->length) ? (int) $json->length : 0;
            $precision = isset($json->precision) ? (int) $json->precision : 0;
            $scale = isset($json->scale) ? (int) $json->scale : 0;
            $nullable = isset($json->nullable) ? (bool) $json->nullable : false;
            $pk = isset($json->pk) ? (bool) $json->pk : false;
            $fk = isset($json->fk) ? $json->fk : '';
            return new ReflectedColumn($name, $realName, $type, $length, $precision, $scale, $nullable, $pk, $fk);
        }
        private function sanitize()
        {
            $this->length = $this->hasLength() ? $this->getLength() : 0;
            $this->precision = $this->hasPrecision() ? $this->getPrecision() : 0;
            $this->scale = $this->hasScale() ? $this->getScale() : 0;
        }
        public function getName(): string
        {
            return $this->name;
        }
        public function getRealName(): string
        {
            return $this->realName;
        }
        public function getNullable(): bool
        {
            return $this->nullable;
        }
        public function getType(): string
        {
            return $this->type;
        }
        public function getLength(): int
        {
            return $this->length ?: self::DEFAULT_LENGTH;
        }
        public function getPrecision(): int
        {
            return $this->precision ?: self::DEFAULT_PRECISION;
        }
        public function getScale(): int
        {
            return $this->scale ?: self::DEFAULT_SCALE;
        }
        public function hasLength(): bool
        {
            return in_array($this->type, ['varchar', 'varbinary']);
        }
        public function hasPrecision(): bool
        {
            return $this->type == 'decimal';
        }
        public function hasScale(): bool
        {
            return $this->type == 'decimal';
        }
        public function isBinary(): bool
        {
            return in_array($this->type, ['blob', 'varbinary']);
        }
        public function isBoolean(): bool
        {
            return $this->type == 'boolean';
        }
        public function isGeometry(): bool
        {
            return $this->type == 'geometry';
        }
        public function isInteger(): bool
        {
            return in_array($this->type, ['integer', 'bigint', 'smallint', 'tinyint']);
        }
        public function isText(): bool
        {
            return in_array($this->type, ['varchar', 'clob']);
        }
        public function setPk($value) /*: void*/
        {
            $this->pk = $value;
        }
        public function getPk(): bool
        {
            return $this->pk;
        }
        public function setFk($value) /*: void*/
        {
            $this->fk = $value;
        }
        public function getFk(): string
        {
            return $this->fk;
        }
        public function serialize()
        {
            $json = [
                'name' => $this->realName,
                'alias' => $this->name != $this->realName ? $this->name : null,
                'type' => $this->type,
                'length' => $this->length,
                'precision' => $this->precision,
                'scale' => $this->scale,
                'nullable' => $this->nullable,
                'pk' => $this->pk,
                'fk' => $this->fk,
            ];
            return array_filter($json);
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->serialize();
        }
    }
}
namespace afcodehub\gatewaypro\Column\Reflection {
    use afcodehub\gatewaypro\Database\GenericReflection;
    class ReflectedDatabase implements \JsonSerializable
    {
        private $tableTypes;
        private $tableRealNames;
        public function __construct(array $tableTypes, array $tableRealNames)
        {
            $this->tableTypes = $tableTypes;
            $this->tableRealNames = $tableRealNames;
        }
        public static function fromReflection(GenericReflection $reflection): ReflectedDatabase
        {
            $tableTypes = [];
            $tableRealNames = [];
            foreach ($reflection->getTables() as $table) {
                $tableName = $table['TABLE_NAME'];
                if (in_array($tableName, $reflection->getIgnoredTables())) {
                    continue;
                }
                $tableTypes[$tableName] = $table['TABLE_TYPE'];
                $tableRealNames[$tableName] = $table['TABLE_REAL_NAME'];
            }
            return new ReflectedDatabase($tableTypes, $tableRealNames);
        }
        public static function fromJson( /* object */$json): ReflectedDatabase
        {
            $tableTypes = (array) $json->types;
            $tableRealNames = (array) $json->realNames;
            return new ReflectedDatabase($tableTypes, $tableRealNames);
        }
        public function hasTable(string $tableName): bool
        {
            return isset($this->tableTypes[$tableName]);
        }
        public function getType(string $tableName): string
        {
            return isset($this->tableTypes[$tableName]) ? $this->tableTypes[$tableName] : '';
        }
        public function getRealName(string $tableName): string
        {
            return isset($this->tableRealNames[$tableName]) ? $this->tableRealNames[$tableName] : '';
        }
        public function getTableNames(): array
        {
            return array_keys($this->tableTypes);
        }
        public function removeTable(string $tableName): bool
        {
            if (!isset($this->tableTypes[$tableName])) {
                return false;
            }
            unset($this->tableTypes[$tableName]);
            unset($this->tableRealNames[$tableName]);
            return true;
        }
        public function serialize()
        {
            return [
                'types' => $this->tableTypes,
                'realNames' => $this->tableRealNames,
            ];
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->serialize();
        }
    }
}
namespace afcodehub\gatewaypro\Column\Reflection {
    use afcodehub\gatewaypro\Database\GenericReflection;
    class ReflectedTable implements \JsonSerializable
    {
        private $name;
        private $realName;
        private $type;
        private $columns;
        private $pk;
        private $fks;
        public function __construct(string $name, string $realName, string $type, array $columns)
        {
            $this->name = $name;
            $this->realName = $realName;
            $this->type = $type;
            // set columns
            $this->columns = [];
            foreach ($columns as $column) {
                $columnName = $column->getName();
                $this->columns[$columnName] = $column;
            }
            // set primary key
            $this->pk = null;
            foreach ($columns as $column) {
                if ($column->getPk() == true) {
                    $this->pk = $column;
                }
            }
            // set foreign keys
            $this->fks = [];
            foreach ($columns as $column) {
                $columnName = $column->getName();
                $referencedTableName = $column->getFk();
                if ($referencedTableName != '') {
                    $this->fks[$columnName] = $referencedTableName;
                }
            }
        }
        public static function fromReflection(GenericReflection $reflection, string $name, string $realName, string $type): ReflectedTable
        {
            // set columns
            $columns = [];
            foreach ($reflection->getTableColumns($name, $type) as $tableColumn) {
                $column = ReflectedColumn::fromReflection($reflection, $tableColumn);
                $columns[$column->getName()] = $column;
            }
            // set primary key
            $columnName = false;
            if ($type == 'view') {
                $columnName = 'id';
            } else {
                $columnNames = $reflection->getTablePrimaryKeys($name);
                if (count($columnNames) == 1) {
                    $columnName = $columnNames[0];
                }
            }
            if ($columnName && isset($columns[$columnName])) {
                $pk = $columns[$columnName];
                $pk->setPk(true);
            }
            // set foreign keys
            if ($type == 'view') {
                $tables = $reflection->getTables();
                foreach ($columns as $columnName => $column) {
                    if (substr($columnName, -3) == '_id') {
                        foreach ($tables as $table) {
                            $tableName = $table['TABLE_NAME'];
                            $suffix = $tableName . '_id';
                            if (substr($columnName, -1 * strlen($suffix)) == $suffix) {
                                $column->setFk($tableName);
                            }
                        }
                    }
                }
            } else {
                $fks = $reflection->getTableForeignKeys($name);
                foreach ($fks as $columnName => $table) {
                    $columns[$columnName]->setFk($table);
                }
            }
            return new ReflectedTable($name, $realName, $type, array_values($columns));
        }
        public static function fromJson( /* object */$json): ReflectedTable
        {
            $name = $json->alias??$json->name;
            $realName = $json->name;
            $type = isset($json->type) ? $json->type : 'table';
            $columns = [];
            if (isset($json->columns) && is_array($json->columns)) {
                foreach ($json->columns as $column) {
                    $columns[] = ReflectedColumn::fromJson($column);
                }
            }
            return new ReflectedTable($name, $realName, $type, $columns);
        }
        public function hasColumn(string $columnName): bool
        {
            return isset($this->columns[$columnName]);
        }
        public function hasPk(): bool
        {
            return $this->pk != null;
        }
        public function getPk() /*: ?ReflectedColumn */
        {
            return $this->pk;
        }
        public function getName(): string
        {
            return $this->name;
        }
        public function getRealName(): string
        {
            return $this->realName;
        }
        public function getType(): string
        {
            return $this->type;
        }
        public function getColumnNames(): array
        {
            return array_keys($this->columns);
        }
        public function getColumn($columnName): ReflectedColumn
        {
            return $this->columns[$columnName];
        }
        public function getFksTo(string $tableName): array
        {
            $columns = array();
            foreach ($this->fks as $columnName => $referencedTableName) {
                if ($tableName == $referencedTableName && !is_null($this->columns[$columnName])) {
                    $columns[] = $this->columns[$columnName];
                }
            }
            return $columns;
        }
        public function removeColumn(string $columnName): bool
        {
            if (!isset($this->columns[$columnName])) {
                return false;
            }
            unset($this->columns[$columnName]);
            return true;
        }
        public function serialize()
        {
            $json = [
                'name' => $this->realName,
                'alias' => $this->name!=$this->realName?$this->name:null,
                'type' => $this->type,
                'columns' => array_values($this->columns),
            ];
            return array_filter($json);
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->serialize();
        }
    }
}
namespace afcodehub\gatewaypro\Column {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Database\GenericDB;
    class DefinitionService
    {
        private $db;
        private $reflection;
        public function __construct(GenericDB $db, ReflectionService $reflection)
        {
            $this->db = $db;
            $this->reflection = $reflection;
        }
        public function updateTable(ReflectedTable $table, /* object */ $changes): bool
        {
            $newTable = ReflectedTable::fromJson((object) array_merge((array) $table->jsonSerialize(), (array) $changes));
            if ($table->getRealName() != $newTable->getRealName()) {
                if (!$this->db->definition()->renameTable($table->getRealName(), $newTable->getRealName())) {
                    return false;
                }
            }
            return true;
        }
        public function updateColumn(ReflectedTable $table, ReflectedColumn $column, /* object */ $changes): bool
        {
            // remove constraints on other column
            $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), (array) $changes));
            if ($newColumn->getPk() != $column->getPk() && $table->hasPk()) {
                $oldColumn = $table->getPk();
                if ($oldColumn->getRealName() != $column->getRealName()) {
                    $oldColumn->setPk(false);
                    if (!$this->db->definition()->removeColumnPrimaryKey($table->getRealName(), $oldColumn->getRealName(), $oldColumn)) {
                        return false;
                    }
                }
            }
            // remove constraints
            $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), ['pk' => false, 'fk' => false]));
            if ($newColumn->getPk() != $column->getPk() && !$newColumn->getPk()) {
                if (!$this->db->definition()->removeColumnPrimaryKey($table->getRealName(), $column->getRealName(), $newColumn)) {
                    return false;
                }
            }
            if ($newColumn->getFk() != $column->getFk() && !$newColumn->getFk()) {
                if (!$this->db->definition()->removeColumnForeignKey($table->getRealName(), $column->getRealName(), $newColumn)) {
                    return false;
                }
            }
            // name and type
            $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), (array) $changes));
            $newColumn->setPk(false);
            $newColumn->setFk('');
            if ($newColumn->getRealName() != $column->getRealName()) {
                if (!$this->db->definition()->renameColumn($table->getRealName(), $column->getRealName(), $newColumn)) {
                    return false;
                }
            }
            if (
                $newColumn->getType() != $column->getType() ||
                $newColumn->getLength() != $column->getLength() ||
                $newColumn->getPrecision() != $column->getPrecision() ||
                $newColumn->getScale() != $column->getScale()
            ) {
                if (!$this->db->definition()->retypeColumn($table->getRealName(), $newColumn->getRealName(), $newColumn)) {
                    return false;
                }
            }
            if ($newColumn->getNullable() != $column->getNullable()) {
                if (!$this->db->definition()->setColumnNullable($table->getRealName(), $newColumn->getRealName(), $newColumn)) {
                    return false;
                }
            }
            // add constraints
            $newColumn = ReflectedColumn::fromJson((object) array_merge((array) $column->jsonSerialize(), (array) $changes));
            if ($newColumn->getFk()) {
                if (!$this->db->definition()->addColumnForeignKey($table->getRealName(), $newColumn->getRealName(), $newColumn)) {
                    return false;
                }
            }
            if ($newColumn->getPk()) {
                if (!$this->db->definition()->addColumnPrimaryKey($table->getRealName(), $newColumn->getRealName(), $newColumn)) {
                    return false;
                }
            }
            return true;
        }
        public function addTable(/* object */$definition)
        {
            $newTable = ReflectedTable::fromJson($definition);
            if (!$this->db->definition()->addTable($newTable)) {
                return false;
            }
            return true;
        }
        public function addColumn(ReflectedTable $table, /* object */ $definition)
        {
            $newColumn = ReflectedColumn::fromJson($definition);
            if (!$this->db->definition()->addColumn($table->getRealName(), $newColumn)) {
                return false;
            }
            if ($newColumn->getFk()) {
                if (!$this->db->definition()->addColumnForeignKey($table->getRealName(), $newColumn->getRealName(), $newColumn)) {
                    return false;
                }
            }
            if ($newColumn->getPk()) {
                if (!$this->db->definition()->addColumnPrimaryKey($table->getRealName(), $newColumn->getRealName(), $newColumn)) {
                    return false;
                }
            }
            return true;
        }
        public function removeTable(ReflectedTable $table)
        {
            if (!$this->db->definition()->removeTable($table->getRealName())) {
                return false;
            }
            return true;
        }
        public function removeColumn(ReflectedTable $table, ReflectedColumn $column)
        {
            if ($column->getPk()) {
                $column->setPk(false);
                if (!$this->db->definition()->removeColumnPrimaryKey($table->getRealName(), $column->getRealName(), $column)) {
                    return false;
                }
            }
            if ($column->getFk()) {
                $column->setFk("");
                if (!$this->db->definition()->removeColumnForeignKey($table->getRealName(), $column->getRealName(), $column)) {
                    return false;
                }
            }
            if (!$this->db->definition()->removeColumn($table->getRealName(), $column->getRealName())) {
                return false;
            }
            return true;
        }
    }
}
namespace afcodehub\gatewaypro\Column {
    use afcodehub\gatewaypro\Cache\Cache;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedDatabase;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Database\GenericDB;
    class ReflectionService
    {
        private $db;
        private $cache;
        private $ttl;
        private $database;
        private $tables;
        public function __construct(GenericDB $db, Cache $cache, int $ttl)
        {
            $this->db = $db;
            $this->cache = $cache;
            $this->ttl = $ttl;
            $this->database = null;
            $this->tables = [];
        }
        private function database(): ReflectedDatabase
        {
            if ($this->database) {
                return $this->database;
            }
            $this->database = $this->loadDatabase(true);
            return $this->database;
        }
        private function loadDatabase(bool $useCache): ReflectedDatabase
        {
            $key = sprintf('%s-ReflectedDatabase', $this->db->getCacheKey());
            $data = $useCache ? $this->cache->get($key) : '';
            if ($data != '') {
                $database = ReflectedDatabase::fromJson(json_decode(gzuncompress($data)));
            } else {
                $database = ReflectedDatabase::fromReflection($this->db->reflection());
                $data = gzcompress(json_encode($database, JSON_UNESCAPED_UNICODE));
                $this->cache->set($key, $data, $this->ttl);
            }
            return $database;
        }
        private function loadTable(string $tableName, bool $useCache): ReflectedTable
        {
            $key = sprintf('%s-ReflectedTable(%s)', $this->db->getCacheKey(), $tableName);
            $data = $useCache ? $this->cache->get($key) : '';
            if ($data != '') {
                $table = ReflectedTable::fromJson(json_decode(gzuncompress($data)));
            } else {
                $tableType = $this->database()->getType($tableName);
                $tableRealName = $this->database()->getRealName($tableName);
                $table = ReflectedTable::fromReflection($this->db->reflection(), $tableName, $tableRealName, $tableType);
                $data = gzcompress(json_encode($table, JSON_UNESCAPED_UNICODE));
                $this->cache->set($key, $data, $this->ttl);
            }
            return $table;
        }
        public function refreshTables()
        {
            $this->database = $this->loadDatabase(false);
        }
        public function refreshTable(string $tableName)
        {
            $this->tables[$tableName] = $this->loadTable($tableName, false);
        }
        public function hasTable(string $tableName): bool
        {
            return $this->database()->hasTable($tableName);
        }
        public function getType(string $tableName): string
        {
            return $this->database()->getType($tableName);
        }
        public function getTable(string $tableName): ReflectedTable
        {
            if (!isset($this->tables[$tableName])) {
                $this->tables[$tableName] = $this->loadTable($tableName, true);
            }
            return $this->tables[$tableName];
        }
        public function getTableNames(): array
        {
            return $this->database()->getTableNames();
        }
        public function removeTable(string $tableName): bool
        {
            unset($this->tables[$tableName]);
            return $this->database()->removeTable($tableName);
        }
    }
}
namespace afcodehub\gatewaypro\Config\Base {
    interface ConfigInterface
    {
        public function getMiddlewares();
        public function getProperty(string $key, $default = '');
    }
}
namespace afcodehub\gatewaypro\Config {
    use afcodehub\gatewaypro\Config\Base\ConfigInterface;
    class Config implements ConfigInterface
    {
        private $values = [
            'driver' => null,
            'address' => null,
            'port' => null,
            'username' => '',
            'password' => '',
            'database' => '',
            'command' => '',
            'tables' => 'all',
            'mapping' => '',
            'middlewares' => 'cors',
            'controllers' => 'records,geojson,openapi,status',
            'customControllers' => '',
            'customOpenApiBuilders' => '',
            'cacheType' => 'TempFile',
            'cachePath' => '',
            'cacheTime' => 10,
            'jsonOptions' => JSON_UNESCAPED_UNICODE,
            'debug' => false,
            'basePath' => '',
            'openApiBase' => '{"info":{"title":"PHP-CRUD-API","version":"1.0.0"}}',
            'geometrySrid' => 4326,
        ];
        public function getUID(): string
        {
            return md5(json_encode($this->values));
        }
        private function getDefaultDriver(array $values): string
        {
            if (isset($values['driver'])) {
                return $values['driver'];
            }
            return 'mysql';
        }
        private function getDefaultPort(string $driver): int
        {
            switch ($driver) {
                case 'mysql':
                    return 3306;
                case 'pgsql':
                    return 5432;
                case 'sqlsrv':
                    return 1433;
                case 'sqlite':
                    return 0;
            }
        }
        private function getDefaultAddress(string $driver): string
        {
            switch ($driver) {
                case 'mysql':
                    return 'localhost';
                case 'pgsql':
                    return 'localhost';
                case 'sqlsrv':
                    return 'localhost';
                case 'sqlite':
                    return 'data.db';
            }
        }
        private function getDriverDefaults(string $driver): array
        {
            return [
                'driver' => $driver,
                'address' => $this->getDefaultAddress($driver),
                'port' => $this->getDefaultPort($driver),
            ];
        }
        private function getEnvironmentVariableName(string $key): string
        {
            $prefix = "PHP_CRUD_API_";
            $suffix = strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('.', '_', $key)));
            return $prefix . $suffix;
        }
        public function getProperty(string $key, $default = '')
        {
            if (strpos($key, 'Handler')) {
                return $this->values[$key] ?? $default;
            }
            $variableName = $this->getEnvironmentVariableName($key);
            return getenv($variableName, true) ?: ($this->values[$key] ?? $default);
        }
        public function __construct(array $values)
        {
            $defaults = array_merge($this->values, $this->getDriverDefaults($this->getDefaultDriver($values)));
            foreach ($defaults as $key => $default) {
                $this->values[$key] = $values[$key] ?? $default;
                $this->values[$key] = $this->getProperty($key);
            }
            $this->values['middlewares'] = array_map('trim', explode(',', $this->values['middlewares']));
            foreach ($values as $key => $value) {
                if (strpos($key, '.') === false) {
                    if (!isset($defaults[$key])) {
                        throw new \Exception("Config has invalid key '$key'");
                    }
                } else {
                    $middleware = substr($key, 0, strpos($key, '.'));
                    if (!in_array($middleware, $this->values['middlewares'])) {
                        throw new \Exception("Config has invalid middleware key '$key'");
                    } else {
                        $this->values[$key] = $value;
                    }
                }
            }
        }
        public function getDriver(): string
        {
            return $this->values['driver'];
        }
        public function getAddress(): string
        {
            return $this->values['address'];
        }
        public function getPort(): int
        {
            return $this->values['port'];
        }
        public function getUsername(): string
        {
            return $this->values['username'];
        }
        public function getPassword(): string
        {
            return $this->values['password'];
        }
        public function getDatabase(): string
        {
            return $this->values['database'];
        }
        public function getCommand(): string
        {
            return $this->values['command'];
        }
        public function getTables(): array
        {
            return array_filter(array_map('trim', explode(',', $this->values['tables'])));
        }
        public function getMapping(): array
        {
            $mapping = array_map(function ($v) {
                return explode('=', $v);
            }, array_filter(array_map('trim', explode(',', $this->values['mapping']))));
            return array_combine(array_column($mapping, 0), array_column($mapping, 1));
        }
        public function getMiddlewares(): array
        {
            return $this->values['middlewares'];
        }
        public function getControllers(): array
        {
            return array_filter(array_map('trim', explode(',', $this->values['controllers'])));
        }
        public function getCustomControllers(): array
        {
            return array_filter(array_map('trim', explode(',', $this->values['customControllers'])));
        }
        public function getCustomOpenApiBuilders(): array
        {
            return array_filter(array_map('trim', explode(',', $this->values['customOpenApiBuilders'])));
        }
        public function getCacheType(): string
        {
            return $this->values['cacheType'];
        }
        public function getCachePath(): string
        {
            return $this->values['cachePath'];
        }
        public function getCacheTime(): int
        {
            return $this->values['cacheTime'];
        }
        public function getJsonOptions(): int
        {
            return $this->values['jsonOptions'];
        }
        public function getDebug(): bool
        {
            return $this->values['debug'];
        }
        public function getBasePath(): string
        {
            return $this->values['basePath'];
        }
        public function getOpenApiBase(): array
        {
            return json_decode($this->values['openApiBase'], true);
        }
        public function getGeometrySrid(): int
        {
            return $this->values['geometrySrid'];
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Cache\Cache;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    class CacheController
    {
        private $cache;
        private $responder;
        public function __construct(Router $router, Responder $responder, Cache $cache)
        {
            $router->register('GET', '/cache/clear', array($this, 'clear'));
            $this->cache = $cache;
            $this->responder = $responder;
        }
        public function clear(ServerRequestInterface $request): ResponseInterface
        {
            return $this->responder->success($this->cache->clear());
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Column\DefinitionService;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class ColumnController
    {
        private $responder;
        private $reflection;
        private $definition;
        public function __construct(Router $router, Responder $responder, ReflectionService $reflection, DefinitionService $definition)
        {
            $router->register('GET', '/columns', array($this, 'getDatabase'));
            $router->register('GET', '/columns/*', array($this, 'getTable'));
            $router->register('GET', '/columns/*/*', array($this, 'getColumn'));
            $router->register('PUT', '/columns/*', array($this, 'updateTable'));
            $router->register('PUT', '/columns/*/*', array($this, 'updateColumn'));
            $router->register('POST', '/columns', array($this, 'addTable'));
            $router->register('POST', '/columns/*', array($this, 'addColumn'));
            $router->register('DELETE', '/columns/*', array($this, 'removeTable'));
            $router->register('DELETE', '/columns/*/*', array($this, 'removeColumn'));
            $this->responder = $responder;
            $this->reflection = $reflection;
            $this->definition = $definition;
        }
        public function getDatabase(ServerRequestInterface $request): ResponseInterface
        {
            $tables = [];
            foreach ($this->reflection->getTableNames() as $table) {
                $tables[] = $this->reflection->getTable($table);
            }
            $database = ['tables' => $tables];
            return $this->responder->success($database);
        }
        public function getTable(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $table = $this->reflection->getTable($tableName);
            return $this->responder->success($table);
        }
        public function getColumn(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            $columnName = RequestUtils::getPathSegment($request, 3);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $table = $this->reflection->getTable($tableName);
            if (!$table->hasColumn($columnName)) {
                return $this->responder->error(ErrorCode::COLUMN_NOT_FOUND, $columnName);
            }
            $column = $table->getColumn($columnName);
            return $this->responder->success($column);
        }
        public function updateTable(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $table = $this->reflection->getTable($tableName);
            $success = $this->definition->updateTable($table, $request->getParsedBody());
            if ($success) {
                $this->reflection->refreshTables();
            }
            return $this->responder->success($success);
        }
        public function updateColumn(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            $columnName = RequestUtils::getPathSegment($request, 3);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $table = $this->reflection->getTable($tableName);
            if (!$table->hasColumn($columnName)) {
                return $this->responder->error(ErrorCode::COLUMN_NOT_FOUND, $columnName);
            }
            $column = $table->getColumn($columnName);
            $success = $this->definition->updateColumn($table, $column, $request->getParsedBody());
            if ($success) {
                $this->reflection->refreshTable($tableName);
            }
            return $this->responder->success($success);
        }
        public function addTable(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = $request->getParsedBody()->name;
            if ($this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_ALREADY_EXISTS, $tableName);
            }
            $success = $this->definition->addTable($request->getParsedBody());
            if ($success) {
                $this->reflection->refreshTables();
            }
            return $this->responder->success($success);
        }
        public function addColumn(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $columnName = $request->getParsedBody()->name;
            $table = $this->reflection->getTable($tableName);
            if ($table->hasColumn($columnName)) {
                return $this->responder->error(ErrorCode::COLUMN_ALREADY_EXISTS, $columnName);
            }
            $success = $this->definition->addColumn($table, $request->getParsedBody());
            if ($success) {
                $this->reflection->refreshTable($tableName);
            }
            return $this->responder->success($success);
        }
        public function removeTable(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $table = $this->reflection->getTable($tableName);
            $success = $this->definition->removeTable($table);
            if ($success) {
                $this->reflection->refreshTables();
            }
            return $this->responder->success($success);
        }
        public function removeColumn(ServerRequestInterface $request): ResponseInterface
        {
            $tableName = RequestUtils::getPathSegment($request, 2);
            $columnName = RequestUtils::getPathSegment($request, 3);
            if (!$this->reflection->hasTable($tableName)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $tableName);
            }
            $table = $this->reflection->getTable($tableName);
            if (!$table->hasColumn($columnName)) {
                return $this->responder->error(ErrorCode::COLUMN_NOT_FOUND, $columnName);
            }
            $column = $table->getColumn($columnName);
            $success = $this->definition->removeColumn($table, $column);
            if ($success) {
                $this->reflection->refreshTable($tableName);
            }
            return $this->responder->success($success);
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\GeoJson\GeoJsonService;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class GeoJsonController
    {
        private $service;
        private $responder;
        public function __construct(Router $router, Responder $responder, GeoJsonService $service)
        {
            $router->register('GET', '/geojson/*', array($this, '_list'));
            $router->register('GET', '/geojson/*/*', array($this, 'read'));
            $this->service = $service;
            $this->responder = $responder;
        }
        public function _list(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            $params = RequestUtils::getParams($request);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            return $this->responder->success($this->service->_list($table, $params));
        }
        public function read(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            if ($this->service->getType($table) != 'table') {
                return $this->responder->error(ErrorCode::OPERATION_NOT_SUPPORTED, __FUNCTION__);
            }
            $id = RequestUtils::getPathSegment($request, 3);
            $params = RequestUtils::getParams($request);
            if (strpos($id, ',') !== false) {
                $ids = explode(',', $id);
                $result = (object) array('type' => 'FeatureCollection', 'features' => array());
                for ($i = 0; $i < count($ids); $i++) {
                    array_push($result->features, $this->service->read($table, $ids[$i], $params));
                }
                return $this->responder->success($result);
            } else {
                $response = $this->service->read($table, $id, $params);
                if ($response === null) {
                    return $this->responder->error(ErrorCode::RECORD_NOT_FOUND, $id);
                }
                return $this->responder->success($response);
            }
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use afcodehub\gatewaypro\Record\Document\ErrorDocument;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\ResponseFactory;
    use afcodehub\gatewaypro\ResponseUtils;
    class JsonResponder implements Responder
    {
        private $jsonOptions;
        private $debug;
        public function __construct(int $jsonOptions, bool $debug)
        {
            $this->jsonOptions = $jsonOptions;
            $this->debug = $debug;
        }
        public function error(int $error, string $argument, $details = null): ResponseInterface
        {
            $document = new ErrorDocument(new ErrorCode($error), $argument, $details);
            return ResponseFactory::fromObject($document->getStatus(), $document, $this->jsonOptions);
        }
        public function success($result): ResponseInterface
        {
            return ResponseFactory::fromObject(ResponseFactory::OK, $result, $this->jsonOptions);
        }
        public function exception($exception): ResponseInterface
        {
            $document = ErrorDocument::fromException($exception, $this->debug);
            $response = ResponseFactory::fromObject($document->getStatus(), $document, $this->jsonOptions);
            if ($this->debug) {
                $response = ResponseUtils::addExceptionHeaders($response, $exception);
            }
            return $response;
        }
        public function multi($results): ResponseInterface
        {
            $documents = array();
            $errors = array();
            $success = true;
            foreach ($results as $i => $result) {
                if ($result instanceof \Throwable) {
                    $documents[$i] = null;
                    $errors[$i] = ErrorDocument::fromException($result, $this->debug);
                    $success = false;
                } else {
                    $documents[$i] = $result;
                    $errors[$i] = new ErrorDocument(new ErrorCode(0), '', null);
                }
            }
            $status = $success ? ResponseFactory::OK : ResponseFactory::FAILED_DEPENDENCY;
            $document = $success ? $documents : $errors;
            $response = ResponseFactory::fromObject($status, $document, $this->jsonOptions);
            foreach ($results as $i => $result) {
                if ($result instanceof \Throwable) {
                    if ($this->debug) {
                        $response = ResponseUtils::addExceptionHeaders($response, $result);
                    }
                }
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\OpenApi\OpenApiService;
    class OpenApiController
    {
        private $openApi;
        private $responder;
        public function __construct(Router $router, Responder $responder, OpenApiService $openApi)
        {
            $router->register('GET', '/openapi', array($this, 'openapi'));
            $this->openApi = $openApi;
            $this->responder = $responder;
        }
        public function openapi(ServerRequestInterface $request): ResponseInterface
        {
            return $this->responder->success($this->openApi->get($request));
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\Record\RecordService;
    use afcodehub\gatewaypro\RequestUtils;
    class RecordController
    {
        private $service;
        private $responder;
        public function __construct(Router $router, Responder $responder, RecordService $service)
        {
            $router->register('GET', '/records/*', array($this, '_list'));
            $router->register('POST', '/records/*', array($this, 'create'));
            $router->register('GET', '/records/*/*', array($this, 'read'));
            $router->register('PUT', '/records/*/*', array($this, 'update'));
            $router->register('DELETE', '/records/*/*', array($this, 'delete'));
            $router->register('PATCH', '/records/*/*', array($this, 'increment'));
            $this->service = $service;
            $this->responder = $responder;
        }
        public function _list(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            $params = RequestUtils::getParams($request);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            return $this->responder->success($this->service->_list($table, $params));
        }
        public function read(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            if (!$this->service->hasPrimaryKey($table)) {
                return $this->responder->error(ErrorCode::PRIMARY_KEY_NOT_FOUND, $table);
            }
            $id = RequestUtils::getPathSegment($request, 3);
            $params = RequestUtils::getParams($request);
            if (strpos($id, ',') !== false) {
                $ids = explode(',', $id);
                $argumentLists = array();
                for ($i = 0; $i < count($ids); $i++) {
                    $argumentLists[] = array($table, $ids[$i], $params);
                }
                return $this->responder->multi($this->multiCall([$this->service, 'read'], $argumentLists));
            } else {
                $response = $this->service->read($table, $id, $params);
                if ($response === null) {
                    return $this->responder->error(ErrorCode::RECORD_NOT_FOUND, $id);
                }
                return $this->responder->success($response);
            }
        }
        private function multiCall(callable $method, array $argumentLists): array
        {
            $result = array();
            $success = true;
            $this->service->beginTransaction();
            foreach ($argumentLists as $arguments) {
                try {
                    $result[] = call_user_func_array($method, $arguments);
                } catch (\Throwable $e) {
                    $success = false;
                    $result[] = $e;
                }
            }
            if ($success) {
                $this->service->commitTransaction();
            } else {
                $this->service->rollBackTransaction();
            }
            return $result;
        }
        public function create(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            if (!$this->service->hasPrimaryKey($table)) {
                return $this->responder->error(ErrorCode::PRIMARY_KEY_NOT_FOUND, $table);
            }
            if ($this->service->getType($table) != 'table') {
                return $this->responder->error(ErrorCode::OPERATION_NOT_SUPPORTED, 'create');
            }
            $record = $request->getParsedBody();
            if ($record === null) {
                return $this->responder->error(ErrorCode::HTTP_MESSAGE_NOT_READABLE, '');
            }
            $params = RequestUtils::getParams($request);
            if (is_array($record)) {
                $argumentLists = array();
                foreach ($record as $r) {
                    $argumentLists[] = array($table, $r, $params);
                }
                return $this->responder->multi($this->multiCall([$this->service, 'create'], $argumentLists));
            } else {
                return $this->responder->success($this->service->create($table, $record, $params));
            }
        }
        public function update(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            if (!$this->service->hasPrimaryKey($table)) {
                return $this->responder->error(ErrorCode::PRIMARY_KEY_NOT_FOUND, $table);
            }
            if ($this->service->getType($table) != 'table') {
                return $this->responder->error(ErrorCode::OPERATION_NOT_SUPPORTED, 'update');
            }
            $id = RequestUtils::getPathSegment($request, 3);
            $params = RequestUtils::getParams($request);
            $record = $request->getParsedBody();
            if ($record === null) {
                return $this->responder->error(ErrorCode::HTTP_MESSAGE_NOT_READABLE, '');
            }
            $ids = explode(',', $id);
            if (is_array($record)) {
                if (count($ids) != count($record)) {
                    return $this->responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
                }
                $argumentLists = array();
                for ($i = 0; $i < count($ids); $i++) {
                    $argumentLists[] = array($table, $ids[$i], $record[$i], $params);
                }
                return $this->responder->multi($this->multiCall([$this->service, 'update'], $argumentLists));
            } else {
                if (count($ids) != 1) {
                    return $this->responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
                }
                return $this->responder->success($this->service->update($table, $id, $record, $params));
            }
        }
        public function delete(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            if (!$this->service->hasPrimaryKey($table)) {
                return $this->responder->error(ErrorCode::PRIMARY_KEY_NOT_FOUND, $table);
            }
            if ($this->service->getType($table) != 'table') {
                return $this->responder->error(ErrorCode::OPERATION_NOT_SUPPORTED, 'delete');
            }
            $id = RequestUtils::getPathSegment($request, 3);
            $params = RequestUtils::getParams($request);
            $ids = explode(',', $id);
            if (count($ids) > 1) {
                $argumentLists = array();
                for ($i = 0; $i < count($ids); $i++) {
                    $argumentLists[] = array($table, $ids[$i], $params);
                }
                return $this->responder->multi($this->multiCall([$this->service, 'delete'], $argumentLists));
            } else {
                return $this->responder->success($this->service->delete($table, $id, $params));
            }
        }
        public function increment(ServerRequestInterface $request): ResponseInterface
        {
            $table = RequestUtils::getPathSegment($request, 2);
            if (!$this->service->hasTable($table)) {
                return $this->responder->error(ErrorCode::TABLE_NOT_FOUND, $table);
            }
            if (!$this->service->hasPrimaryKey($table)) {
                return $this->responder->error(ErrorCode::PRIMARY_KEY_NOT_FOUND, $table);
            }
            if ($this->service->getType($table) != 'table') {
                return $this->responder->error(ErrorCode::OPERATION_NOT_SUPPORTED, 'increment');
            }
            $id = RequestUtils::getPathSegment($request, 3);
            $record = $request->getParsedBody();
            if ($record === null) {
                return $this->responder->error(ErrorCode::HTTP_MESSAGE_NOT_READABLE, '');
            }
            $params = RequestUtils::getParams($request);
            $ids = explode(',', $id);
            if (is_array($record)) {
                if (count($ids) != count($record)) {
                    return $this->responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
                }
                $argumentLists = array();
                for ($i = 0; $i < count($ids); $i++) {
                    $argumentLists[] = array($table, $ids[$i], $record[$i], $params);
                }
                return $this->responder->multi($this->multiCall([$this->service, 'increment'], $argumentLists));
            } else {
                if (count($ids) != 1) {
                    return $this->responder->error(ErrorCode::ARGUMENT_COUNT_MISMATCH, $id);
                }
                return $this->responder->success($this->service->increment($table, $id, $record, $params));
            }
        }
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    interface Responder
    {
        public function error(int $error, string $argument, $details = null): ResponseInterface;
        public function success($result): ResponseInterface;
        public function multi($results): ResponseInterface;
        public function exception($exception): ResponseInterface;
    }
}
namespace afcodehub\gatewaypro\Controller {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Cache\Cache;
    use afcodehub\gatewaypro\Database\GenericDB;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    class StatusController
    {
        private $db;
        private $cache;
        private $responder;
        public function __construct(Router $router, Responder $responder, Cache $cache, GenericDB $db)
        {
            $router->register('GET', '/status/ping', array($this, 'ping'));
            $this->db = $db;
            $this->cache = $cache;
            $this->responder = $responder;
        }
        public function ping(ServerRequestInterface $request): ResponseInterface
        {
            $result = [
                'db' => $this->db->ping(),
                'cache' => $this->cache->ping(),
            ];
            return $this->responder->success($result);
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    class ColumnConverter
    {
        private $driver;
        private $geometrySrid;
        public function __construct(string $driver, int $geometrySrid)
        {
            $this->driver = $driver;
            $this->geometrySrid = $geometrySrid;
        }
        public function convertColumnValue(ReflectedColumn $column): string
        {
            if ($column->isBoolean()) {
                switch ($this->driver) {
                    case 'mysql':
                        return "IFNULL(IF(?,TRUE,FALSE),NULL)";
                    case 'pgsql':
                        return "?";
                    case 'sqlsrv':
                        return "?";
                }
            }
            if ($column->isBinary()) {
                switch ($this->driver) {
                    case 'mysql':
                        return "FROM_BASE64(?)";
                    case 'pgsql':
                        return "decode(?, 'base64')";
                    case 'sqlsrv':
                        return "CONVERT(XML, ?).value('.','varbinary(max)')";
                }
            }
            if ($column->isGeometry()) {
                $srid = $this->geometrySrid;
                switch ($this->driver) {
                    case 'mysql':
                    case 'pgsql':
                        return "ST_GeomFromText(?,$srid)";
                    case 'sqlsrv':
                        return "geometry::STGeomFromText(?,$srid)";
                }
            }
            return '?';
        }
        public function convertColumnName(ReflectedColumn $column, $value): string
        {
            if ($column->isBinary()) {
                switch ($this->driver) {
                    case 'mysql':
                        return "TO_BASE64($value) as $value";
                    case 'pgsql':
                        return "encode($value::bytea, 'base64') as $value";
                    case 'sqlsrv':
                        return "CASE WHEN $value IS NULL THEN NULL ELSE (SELECT CAST($value as varbinary(max)) FOR XML PATH(''), BINARY BASE64) END as $value";
                }
            }
            if ($column->isGeometry()) {
                switch ($this->driver) {
                    case 'mysql':
                    case 'pgsql':
                        return "ST_AsText($value) as $value";
                    case 'sqlsrv':
                        return "REPLACE($value.STAsText(),' (','(') as $value";
                }
            }
            return $value;
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    class ColumnsBuilder
    {
        private $driver;
        private $converter;
        public function __construct(string $driver, int $geometrySrid)
        {
            $this->driver = $driver;
            $this->converter = new ColumnConverter($driver, $geometrySrid);
        }
        public function getOffsetLimit(int $offset, int $limit): string
        {
            if ($limit < 0 || $offset < 0) {
                return '';
            }
            switch ($this->driver) {
                case 'mysql':
                    return " LIMIT $offset, $limit";
                case 'pgsql':
                    return " LIMIT $limit OFFSET $offset";
                case 'sqlsrv':
                    return " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
                case 'sqlite':
                    return " LIMIT $limit OFFSET $offset";
            }
        }
        private function quoteColumnName(ReflectedColumn $column): string
        {
            return '"' . $column->getRealName() . '"';
        }
        public function getOrderBy(ReflectedTable $table, array $columnOrdering): string
        {
            if (count($columnOrdering) == 0) {
                return '';
            }
            $results = array();
            foreach ($columnOrdering as $i => list($columnName, $ordering)) {
                $column = $table->getColumn($columnName);
                $quotedColumnName = $this->quoteColumnName($column);
                $results[] = $quotedColumnName . ' ' . $ordering;
            }
            return ' ORDER BY ' . implode(',', $results);
        }
        public function getSelect(ReflectedTable $table, array $columnNames): string
        {
            $results = array();
            foreach ($columnNames as $columnName) {
                $column = $table->getColumn($columnName);
                $quotedColumnName = $this->quoteColumnName($column);
                $quotedColumnName = $this->converter->convertColumnName($column, $quotedColumnName);
                $results[] = $quotedColumnName;
            }
            return implode(',', $results);
        }
        public function getInsert(ReflectedTable $table, array $columnValues): string
        {
            $columns = array();
            $values = array();
            foreach ($columnValues as $columnName => $columnValue) {
                $column = $table->getColumn($columnName);
                $quotedColumnName = $this->quoteColumnName($column);
                $columns[] = $quotedColumnName;
                $columnValue = $this->converter->convertColumnValue($column);
                $values[] = $columnValue;
            }
            $columnsSql = '(' . implode(',', $columns) . ')';
            $valuesSql = '(' . implode(',', $values) . ')';
            $outputColumn = $this->quoteColumnName($table->getPk());
            switch ($this->driver) {
                case 'mysql':
                    return "$columnsSql VALUES $valuesSql";
                case 'pgsql':
                    return "$columnsSql VALUES $valuesSql RETURNING $outputColumn";
                case 'sqlsrv':
                    return "$columnsSql OUTPUT INSERTED.$outputColumn VALUES $valuesSql";
                case 'sqlite':
                    return "$columnsSql VALUES $valuesSql";
            }
        }
        public function getUpdate(ReflectedTable $table, array $columnValues): string
        {
            $results = array();
            foreach ($columnValues as $columnName => $columnValue) {
                $column = $table->getColumn($columnName);
                $quotedColumnName = $this->quoteColumnName($column);
                $columnValue = $this->converter->convertColumnValue($column);
                $results[] = $quotedColumnName . '=' . $columnValue;
            }
            return implode(',', $results);
        }
        public function getIncrement(ReflectedTable $table, array $columnValues): string
        {
            $results = array();
            foreach ($columnValues as $columnName => $columnValue) {
                if (!is_numeric($columnValue)) {
                    continue;
                }
                $column = $table->getColumn($columnName);
                $quotedColumnName = $this->quoteColumnName($column);
                $columnValue = $this->converter->convertColumnValue($column);
                $results[] = $quotedColumnName . '=' . $quotedColumnName . '+' . $columnValue;
            }
            return implode(',', $results);
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Record\Condition\AndCondition;
    use afcodehub\gatewaypro\Record\Condition\ColumnCondition;
    use afcodehub\gatewaypro\Record\Condition\Condition;
    use afcodehub\gatewaypro\Record\Condition\NoCondition;
    use afcodehub\gatewaypro\Record\Condition\NotCondition;
    use afcodehub\gatewaypro\Record\Condition\OrCondition;
    use afcodehub\gatewaypro\Record\Condition\SpatialCondition;
    class ConditionsBuilder
    {
        private $driver;
        private $geometrySrid;
        public function __construct(string $driver, int $geometrySrid)
        {
            $this->driver = $driver;
            $this->geometrySrid = $geometrySrid;
        }
        private function getConditionSql(Condition $condition, array &$arguments): string
        {
            if ($condition instanceof AndCondition) {
                return $this->getAndConditionSql($condition, $arguments);
            }
            if ($condition instanceof OrCondition) {
                return $this->getOrConditionSql($condition, $arguments);
            }
            if ($condition instanceof NotCondition) {
                return $this->getNotConditionSql($condition, $arguments);
            }
            if ($condition instanceof SpatialCondition) {
                return $this->getSpatialConditionSql($condition, $arguments);
            }
            if ($condition instanceof ColumnCondition) {
                return $this->getColumnConditionSql($condition, $arguments);
            }
            throw new \Exception('Unknown Condition: ' . get_class($condition));
        }
        private function getAndConditionSql(AndCondition $and, array &$arguments): string
        {
            $parts = [];
            foreach ($and->getConditions() as $condition) {
                $parts[] = $this->getConditionSql($condition, $arguments);
            }
            return '(' . implode(' AND ', $parts) . ')';
        }
        private function getOrConditionSql(OrCondition $or, array &$arguments): string
        {
            $parts = [];
            foreach ($or->getConditions() as $condition) {
                $parts[] = $this->getConditionSql($condition, $arguments);
            }
            return '(' . implode(' OR ', $parts) . ')';
        }
        private function getNotConditionSql(NotCondition $not, array &$arguments): string
        {
            $condition = $not->getCondition();
            return '(NOT ' . $this->getConditionSql($condition, $arguments) . ')';
        }
        private function quoteColumnName(ReflectedColumn $column): string
        {
            return '"' . $column->getRealName() . '"';
        }
        private function escapeLikeValue(string $value): string
        {
            return addcslashes($value, '%_');
        }
        private function getColumnConditionSql(ColumnCondition $condition, array &$arguments): string
        {
            $column = $this->quoteColumnName($condition->getColumn());
            $operator = $condition->getOperator();
            $value = $condition->getValue();
            switch ($operator) {
                case 'cs':
                    $sql = "$column LIKE ?";
                    $arguments[] = '%' . $this->escapeLikeValue($value) . '%';
                    break;
                case 'sw':
                    $sql = "$column LIKE ?";
                    $arguments[] = $this->escapeLikeValue($value) . '%';
                    break;
                case 'ew':
                    $sql = "$column LIKE ?";
                    $arguments[] = '%' . $this->escapeLikeValue($value);
                    break;
                case 'eq':
                    $sql = "$column = ?";
                    $arguments[] = $value;
                    break;
                case 'lt':
                    $sql = "$column < ?";
                    $arguments[] = $value;
                    break;
                case 'le':
                    $sql = "$column <= ?";
                    $arguments[] = $value;
                    break;
                case 'ge':
                    $sql = "$column >= ?";
                    $arguments[] = $value;
                    break;
                case 'gt':
                    $sql = "$column > ?";
                    $arguments[] = $value;
                    break;
                case 'bt':
                    $parts = explode(',', $value, 2);
                    $count = count($parts);
                    if ($count == 2) {
                        $sql = "($column >= ? AND $column <= ?)";
                        $arguments[] = $parts[0];
                        $arguments[] = $parts[1];
                    } else {
                        $sql = "FALSE";
                    }
                    break;
                case 'in':
                    $parts = explode(',', $value);
                    $count = count($parts);
                    if ($count > 0) {
                        $qmarks = implode(',', str_split(str_repeat('?', $count)));
                        $sql = "$column IN ($qmarks)";
                        for ($i = 0; $i < $count; $i++) {
                            $arguments[] = $parts[$i];
                        }
                    } else {
                        $sql = "FALSE";
                    }
                    break;
                case 'is':
                    $sql = "$column IS NULL";
                    break;
            }
            return $sql;
        }
        private function getSpatialFunctionName(string $operator): string
        {
            switch ($operator) {
                case 'co':
                    return 'ST_Contains';
                case 'cr':
                    return 'ST_Crosses';
                case 'di':
                    return 'ST_Disjoint';
                case 'eq':
                    return 'ST_Equals';
                case 'in':
                    return 'ST_Intersects';
                case 'ov':
                    return 'ST_Overlaps';
                case 'to':
                    return 'ST_Touches';
                case 'wi':
                    return 'ST_Within';
                case 'ic':
                    return 'ST_IsClosed';
                case 'is':
                    return 'ST_IsSimple';
                case 'iv':
                    return 'ST_IsValid';
            }
        }
        private function hasSpatialArgument(string $operator): bool
        {
            return in_array($operator, ['ic', 'is', 'iv']) ? false : true;
        }
        private function getSpatialFunctionCall(string $functionName, string $column, bool $hasArgument): string
        {
            $srid = $this->geometrySrid;
            switch ($this->driver) {
                case 'mysql':
                case 'pgsql':
                    $argument = $hasArgument ? "ST_GeomFromText(?,$srid)" : '';
                    return "$functionName($column, $argument)=TRUE";
                case 'sqlsrv':
                    $functionName = str_replace('ST_', 'ST', $functionName);
                    $argument = $hasArgument ? "geometry::STGeomFromText(?,$srid)" : '';
                    return "$column.$functionName($argument)=1";
                case 'sqlite':
                    $argument = $hasArgument ? '?' : '0';
                    return "$functionName($column, $argument)=1";
            }
        }
        private function getSpatialConditionSql(ColumnCondition $condition, array &$arguments): string
        {
            $column = $this->quoteColumnName($condition->getColumn());
            $operator = $condition->getOperator();
            $value = $condition->getValue();
            $functionName = $this->getSpatialFunctionName($operator);
            $hasArgument = $this->hasSpatialArgument($operator);
            $sql = $this->getSpatialFunctionCall($functionName, $column, $hasArgument);
            if ($hasArgument) {
                $arguments[] = $value;
            }
            return $sql;
        }
        public function getWhereClause(Condition $condition, array &$arguments): string
        {
            if ($condition instanceof NoCondition) {
                return '';
            }
            return ' WHERE ' . $this->getConditionSql($condition, $arguments);
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    class DataConverter
    {
        private $driver;
        public function __construct(string $driver)
        {
            $this->driver = $driver;
        }
        private function convertRecordValue($conversion, $value)
        {
            $args = explode('|', $conversion);
            $type = array_shift($args);
            switch ($type) {
                case 'boolean':
                    return $value ? true : false;
                case 'integer':
                    return (int) $value;
                case 'float':
                    return (float) $value;
                case 'decimal':
                    return number_format($value, $args[0], '.', '');
            }
            return $value;
        }
        private function getRecordValueConversion(ReflectedColumn $column): string
        {
            if ($column->isBoolean()) {
                return 'boolean';
            }
            if (in_array($column->getType(), ['integer', 'bigint'])) {
                return 'integer';
            }
            if (in_array($column->getType(), ['float', 'double'])) {
                return 'float';
            }
            if (in_array($this->driver, ['sqlite']) && in_array($column->getType(), ['decimal'])) {
                return 'decimal|' . $column->getScale();
            }
            return 'none';
        }
        public function convertRecords(ReflectedTable $table, array $columnNames, array &$records) /*: void*/
        {
            foreach ($columnNames as $columnName) {
                $column = $table->getColumn($columnName);
                $conversion = $this->getRecordValueConversion($column);
                if ($conversion != 'none') {
                    foreach ($records as $i => $record) {
                        $value = $records[$i][$columnName];
                        if ($value === null) {
                            continue;
                        }
                        $records[$i][$columnName] = $this->convertRecordValue($conversion, $value);
                    }
                }
            }
        }
        private function convertInputValue($conversion, $value)
        {
            switch ($conversion) {
                case 'boolean':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                case 'base64url_to_base64':
                    return str_pad(strtr($value, '-_', '+/'), ceil(strlen($value) / 4) * 4, '=', STR_PAD_RIGHT);
            }
            return $value;
        }
        private function getInputValueConversion(ReflectedColumn $column): string
        {
            if ($column->isBoolean()) {
                return 'boolean';
            }
            if ($column->isBinary()) {
                return 'base64url_to_base64';
            }
            return 'none';
        }
        public function convertColumnValues(ReflectedTable $table, array &$columnValues) /*: void*/
        {
            $columnNames = array_keys($columnValues);
            foreach ($columnNames as $columnName) {
                $column = $table->getColumn($columnName);
                $conversion = $this->getInputValueConversion($column);
                if ($conversion != 'none') {
                    $value = $columnValues[$columnName];
                    if ($value !== null) {
                        $columnValues[$columnName] = $this->convertInputValue($conversion, $value);
                    }
                }
            }
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Middleware\Communication\VariableStore;
    use afcodehub\gatewaypro\Record\Condition\ColumnCondition;
    use afcodehub\gatewaypro\Record\Condition\Condition;
    class GenericDB
    {
        private $driver;
        private $address;
        private $port;
        private $database;
        private $command;
        private $tables;
        private $mapping;
        private $username;
        private $password;
        private $pdo;
        private $mapper;
        private $reflection;
        private $definition;
        private $conditions;
        private $columns;
        private $converter;
        private $geometrySrid;
        private function getDsn(): string
        {
            switch ($this->driver) {
                case 'mysql':
                    return "$this->driver:host=$this->address;port=$this->port;dbname=$this->database;charset=utf8mb4";
                case 'pgsql':
                    return "$this->driver:host=$this->address port=$this->port dbname=$this->database options='--client_encoding=UTF8'";
                case 'sqlsrv':
                    return "$this->driver:Server=$this->address,$this->port;Database=$this->database";
                case 'sqlite':
                    return "$this->driver:$this->address";
            }
        }
        private function getCommands(): array
        {
            switch ($this->driver) {
                case 'mysql':
                    $commands = [
                        'SET SESSION sql_warnings=1;',
                        'SET NAMES utf8mb4;',
                        'SET SESSION sql_mode = "ANSI,TRADITIONAL";',
                    ];
                    break;
                case 'pgsql':
                    $commands = [
                        "SET NAMES 'UTF8';",
                    ];
                    break;
                case 'sqlsrv':
                    $commands = [];
                    break;
                case 'sqlite':
                    $commands = [
                        'PRAGMA foreign_keys = on;',
                    ];
                    break;
            }
            if ($this->command != '') {
                $commands[] = $this->command;
            }
            return $commands;
        }
        private function getOptions(): array
        {
            $options = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            );
            switch ($this->driver) {
                case 'mysql':
                    return $options + [
                        \PDO::MYSQL_ATTR_FOUND_ROWS => true,
                        \PDO::ATTR_PERSISTENT => true,
                    ];
                case 'pgsql':
                    return $options + [
                        \PDO::ATTR_PERSISTENT => true,
                    ];
                case 'sqlsrv':
                    return $options + [];
                case 'sqlite':
                    return $options + [];
            }
        }
        private function initPdo(): bool
        {
            if ($this->pdo) {
                $result = $this->pdo->reconstruct($this->getDsn(), $this->username, $this->password, $this->getOptions());
            } else {
                $this->pdo = new LazyPdo($this->getDsn(), $this->username, $this->password, $this->getOptions());
                $result = true;
            }
            $commands = $this->getCommands();
            foreach ($commands as $command) {
                $this->pdo->addInitCommand($command);
            }
            $this->mapper = new RealNameMapper($this->mapping);
            $this->reflection = new GenericReflection($this->pdo, $this->driver, $this->database, $this->tables, $this->mapper);
            $this->definition = new GenericDefinition($this->pdo, $this->driver, $this->database, $this->tables, $this->mapper);
            $this->conditions = new ConditionsBuilder($this->driver, $this->geometrySrid);
            $this->columns = new ColumnsBuilder($this->driver, $this->geometrySrid);
            $this->converter = new DataConverter($this->driver);
            return $result;
        }
        public function __construct(string $driver, string $address, int $port, string $database, string $command, array $tables, array $mapping, string $username, string $password, int $geometrySrid)
        {
            $this->driver = $driver;
            $this->address = $address;
            $this->port = $port;
            $this->database = $database;
            $this->command = $command;
            $this->tables = $tables;
            $this->mapping = $mapping;
            $this->username = $username;
            $this->password = $password;
            $this->geometrySrid = $geometrySrid;
            $this->initPdo();
        }
        public function reconstruct(string $driver, string $address, int $port, string $database, string $command, array $tables, array $mapping, string $username, string $password, int $geometrySrid): bool
        {
            if ($driver) {
                $this->driver = $driver;
            }
            if ($address) {
                $this->address = $address;
            }
            if ($port) {
                $this->port = $port;
            }
            if ($database) {
                $this->database = $database;
            }
            if ($command) {
                $this->command = $command;
            }
            if ($tables) {
                $this->tables = $tables;
            }
            if ($mapping) {
                $this->mapping = $mapping;
            }
            if ($username) {
                $this->username = $username;
            }
            if ($password) {
                $this->password = $password;
            }
            if ($geometrySrid) {
                $this->geometrySrid = $geometrySrid;
            }
            return $this->initPdo();
        }
        public function pdo(): LazyPdo
        {
            return $this->pdo;
        }
        public function reflection(): GenericReflection
        {
            return $this->reflection;
        }
        public function definition(): GenericDefinition
        {
            return $this->definition;
        }
        public function beginTransaction() /*: void*/
        {
            $this->pdo->beginTransaction();
        }
        public function commitTransaction() /*: void*/
        {
            $this->pdo->commit();
        }
        public function rollBackTransaction() /*: void*/
        {
            $this->pdo->rollBack();
        }
        private function addMiddlewareConditions(string $tableName, Condition $condition): Condition
        {
            $condition1 = VariableStore::get("authorization.conditions.$tableName");
            if ($condition1) {
                $condition = $condition->_and($condition1);
            }
            $condition2 = VariableStore::get("multiTenancy.conditions.$tableName");
            if ($condition2) {
                $condition = $condition->_and($condition2);
            }
            return $condition;
        }
        public function createSingle(ReflectedTable $table, array $columnValues) /*: ?String*/
        {
            $this->converter->convertColumnValues($table, $columnValues);
            $insertColumns = $this->columns->getInsert($table, $columnValues);
            $tableRealName = $table->getRealName();
            $pkName = $table->getPk()->getName();
            $parameters = array_values($columnValues);
            $sql = 'INSERT INTO "' . $tableRealName . '" ' . $insertColumns;
            $stmt = $this->query($sql, $parameters);
            // return primary key value if specified in the input
            if (isset($columnValues[$pkName])) {
                return $columnValues[$pkName];
            }
            // work around missing "returning" or "output" in mysql
            switch ($this->driver) {
                case 'mysql':
                    $stmt = $this->query('SELECT LAST_INSERT_ID()', []);
                    break;
                case 'sqlite':
                    $stmt = $this->query('SELECT LAST_INSERT_ROWID()', []);
                    break;
            }
            $pkValue = $stmt->fetchColumn(0);
            if ($table->getPk()->getType() == 'bigint') {
                return (int) $pkValue;
            }
            if (in_array($table->getPk()->getType(), ['integer', 'bigint'])) {
                return (int) $pkValue;
            }
            return $pkValue;
        }
        public function selectSingle(ReflectedTable $table, array $columnNames, string $id) /*: ?array*/
        {
            $selectColumns = $this->columns->getSelect($table, $columnNames);
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = new ColumnCondition($table->getPk(), 'eq', $id);
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array();
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $sql = 'SELECT ' . $selectColumns . ' FROM "' . $tableRealName . '" ' . $whereClause;
            $stmt = $this->query($sql, $parameters);
            $record = $stmt->fetch() ?: null;
            if ($record === null) {
                return null;
            }
            $records = array($record);
            $records = $this->mapRecords($tableRealName, $records);
            $this->converter->convertRecords($table, $columnNames, $records);
            return $records[0];
        }
        public function selectMultiple(ReflectedTable $table, array $columnNames, array $ids): array
        {
            if (count($ids) == 0) {
                return [];
            }
            $selectColumns = $this->columns->getSelect($table, $columnNames);
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = new ColumnCondition($table->getPk(), 'in', implode(',', $ids));
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array();
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $sql = 'SELECT ' . $selectColumns . ' FROM "' . $tableRealName . '" ' . $whereClause;
            $stmt = $this->query($sql, $parameters);
            $records = $stmt->fetchAll();
            $records = $this->mapRecords($tableRealName, $records);
            $this->converter->convertRecords($table, $columnNames, $records);
            return $records;
        }
        public function selectCount(ReflectedTable $table, Condition $condition): int
        {
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array();
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $sql = 'SELECT COUNT(*) FROM "' . $tableRealName . '"' . $whereClause;
            $stmt = $this->query($sql, $parameters);
            return $stmt->fetchColumn(0);
        }
        private function mapRecords(string $tableRealName, array $records): array
        {
            $mappedRecords = [];
            foreach ($records as $record) {
                $mappedRecord = [];
                foreach ($record as $columnRealName => $columnValue) {
                    $mappedRecord[$this->mapper->getColumnName($tableRealName, $columnRealName)] = $columnValue;
                }
                $mappedRecords[] = $mappedRecord;
            }
            return $mappedRecords;
        }
        public function selectAll(ReflectedTable $table, array $columnNames, Condition $condition, array $columnOrdering, int $offset, int $limit): array
        {
            if ($limit == 0) {
                return array();
            }
            $selectColumns = $this->columns->getSelect($table, $columnNames);
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array();
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $orderBy = $this->columns->getOrderBy($table, $columnOrdering);
            $offsetLimit = $this->columns->getOffsetLimit($offset, $limit);
            $sql = 'SELECT ' . $selectColumns . ' FROM "' . $tableRealName . '"' . $whereClause . $orderBy . $offsetLimit;
            $stmt = $this->query($sql, $parameters);
            $records = $stmt->fetchAll();
            $records = $this->mapRecords($tableRealName, $records);
            $this->converter->convertRecords($table, $columnNames, $records);
            return $records;
        }
        public function updateSingle(ReflectedTable $table, array $columnValues, string $id)
        {
            if (count($columnValues) == 0) {
                return 0;
            }
            $this->converter->convertColumnValues($table, $columnValues);
            $updateColumns = $this->columns->getUpdate($table, $columnValues);
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = new ColumnCondition($table->getPk(), 'eq', $id);
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array_values($columnValues);
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $sql = 'UPDATE "' . $tableRealName . '" SET ' . $updateColumns . $whereClause;
            $stmt = $this->query($sql, $parameters);
            return $stmt->rowCount();
        }
        public function deleteSingle(ReflectedTable $table, string $id)
        {
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = new ColumnCondition($table->getPk(), 'eq', $id);
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array();
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $sql = 'DELETE FROM "' . $tableRealName . '" ' . $whereClause;
            $stmt = $this->query($sql, $parameters);
            return $stmt->rowCount();
        }
        public function incrementSingle(ReflectedTable $table, array $columnValues, string $id)
        {
            if (count($columnValues) == 0) {
                return 0;
            }
            $this->converter->convertColumnValues($table, $columnValues);
            $updateColumns = $this->columns->getIncrement($table, $columnValues);
            $tableName = $table->getName();
            $tableRealName = $table->getRealName();
            $condition = new ColumnCondition($table->getPk(), 'eq', $id);
            $condition = $this->addMiddlewareConditions($tableName, $condition);
            $parameters = array_values($columnValues);
            $whereClause = $this->conditions->getWhereClause($condition, $parameters);
            $sql = 'UPDATE "' . $tableRealName . '" SET ' . $updateColumns . $whereClause;
            $stmt = $this->query($sql, $parameters);
            return $stmt->rowCount();
        }
        private function query(string $sql, array $parameters): \PDOStatement
        {
            $stmt = $this->pdo->prepare($sql);
            //echo "- $sql -- " . json_encode($parameters, JSON_UNESCAPED_UNICODE) . "\n";
            $stmt->execute($parameters);
            return $stmt;
        }
        public function ping(): int
        {
            $start = microtime(true);
            $stmt = $this->pdo->prepare('SELECT 1');
            $stmt->execute();
            return intval((microtime(true) - $start) * 1000000);
        }
        public function getCacheKey(): string
        {
            return md5(json_encode([
                $this->driver,
                $this->address,
                $this->port,
                $this->database,
                $this->tables,
                $this->mapping,
                $this->username,
            ]));
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Database\LazyPdo;
    class GenericDefinition
    {
        private $pdo;
        private $driver;
        private $database;
        private $typeConverter;
        private $reflection;
        public function __construct(LazyPdo $pdo, string $driver, string $database, array $tables, RealNameMapper $mapper)
        {
            $this->pdo = $pdo;
            $this->driver = $driver;
            $this->database = $database;
            $this->typeConverter = new TypeConverter($driver);
            $this->reflection = new GenericReflection($pdo, $driver, $database, $tables, $mapper);
        }
        private function quote(string $identifier): string
        {
            return '"' . str_replace('"', '', $identifier) . '"';
        }
        public function getColumnType(ReflectedColumn $column, bool $update): string
        {
            if ($this->driver == 'pgsql' && !$update && $column->getPk() && $this->canAutoIncrement($column)) {
                return 'serial';
            }
            $type = $this->typeConverter->fromJdbc($column->getType());
            if ($column->hasPrecision() && $column->hasScale()) {
                $size = '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
            } elseif ($column->hasPrecision()) {
                $size = '(' . $column->getPrecision() . ')';
            } elseif ($column->hasLength()) {
                $size = '(' . $column->getLength() . ')';
            } else {
                $size = '';
            }
            $null = $this->getColumnNullType($column, $update);
            $auto = $this->getColumnAutoIncrement($column, $update);
            return $type . $size . $null . $auto;
        }
        private function getPrimaryKey(string $tableName): string
        {
            $pks = $this->reflection->getTablePrimaryKeys($tableName);
            if (count($pks) == 1) {
                return $pks[0];
            }
            return "";
        }
        private function canAutoIncrement(ReflectedColumn $column): bool
        {
            return in_array($column->getType(), ['integer', 'bigint']);
        }
        private function getColumnAutoIncrement(ReflectedColumn $column, bool $update): string
        {
            if (!$this->canAutoIncrement($column)) {
                return '';
            }
            switch ($this->driver) {
                case 'mysql':
                    return $column->getPk() ? ' AUTO_INCREMENT' : '';
                case 'pgsql':
                case 'sqlsrv':
                    return $column->getPk() ? ' IDENTITY(1,1)' : '';
                case 'sqlite':
                    return $column->getPk() ? ' AUTOINCREMENT' : '';
            }
        }
        private function getColumnNullType(ReflectedColumn $column, bool $update): string
        {
            if ($this->driver == 'pgsql' && $update) {
                return '';
            }
            return $column->getNullable() ? ' NULL' : ' NOT NULL';
        }
        private function getTableRenameSQL(string $tableName, string $newTableName): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($newTableName);
            switch ($this->driver) {
                case 'mysql':
                    return "RENAME TABLE $p1 TO $p2";
                case 'pgsql':
                    return "ALTER TABLE $p1 RENAME TO $p2";
                case 'sqlsrv':
                    return "EXEC sp_rename $p1, $p2";
                case 'sqlite':
                    return "ALTER TABLE $p1 RENAME TO $p2";
            }
        }
        private function getColumnRenameSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            $p3 = $this->quote($newColumn->getRealName());
            switch ($this->driver) {
                case 'mysql':
                    $p4 = $this->getColumnType($newColumn, true);
                    return "ALTER TABLE $p1 CHANGE $p2 $p3 $p4";
                case 'pgsql':
                    return "ALTER TABLE $p1 RENAME COLUMN $p2 TO $p3";
                case 'sqlsrv':
                    $p4 = $this->quote($tableName . '.' . $columnName);
                    return "EXEC sp_rename $p4, $p3, 'COLUMN'";
                case 'sqlite':
                    return "ALTER TABLE $p1 RENAME COLUMN $p2 TO $p3";
            }
        }
        private function getColumnRetypeSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            $p3 = $this->quote($newColumn->getRealName());
            $p4 = $this->getColumnType($newColumn, true);
            switch ($this->driver) {
                case 'mysql':
                    return "ALTER TABLE $p1 CHANGE $p2 $p3 $p4";
                case 'pgsql':
                    return "ALTER TABLE $p1 ALTER COLUMN $p3 TYPE $p4";
                case 'sqlsrv':
                    return "ALTER TABLE $p1 ALTER COLUMN $p3 $p4";
            }
        }
        private function getSetColumnNullableSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            $p3 = $this->quote($newColumn->getRealName());
            $p4 = $this->getColumnType($newColumn, true);
            switch ($this->driver) {
                case 'mysql':
                    return "ALTER TABLE $p1 CHANGE $p2 $p3 $p4";
                case 'pgsql':
                    $p5 = $newColumn->getNullable() ? 'DROP NOT NULL' : 'SET NOT NULL';
                    return "ALTER TABLE $p1 ALTER COLUMN $p2 $p5";
                case 'sqlsrv':
                    return "ALTER TABLE $p1 ALTER COLUMN $p2 $p4";
            }
        }
        private function getSetColumnPkConstraintSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            $p3 = $this->quote($tableName . '_pkey');
            switch ($this->driver) {
                case 'mysql':
                    $p4 = $newColumn->getPk() ? "ADD PRIMARY KEY ($p2)" : 'DROP PRIMARY KEY';
                    return "ALTER TABLE $p1 $p4";
                case 'pgsql':
                case 'sqlsrv':
                    $p4 = $newColumn->getPk() ? "ADD CONSTRAINT $p3 PRIMARY KEY ($p2)" : "DROP CONSTRAINT $p3";
                    return "ALTER TABLE $p1 $p4";
            }
        }
        private function getSetColumnPkSequenceSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            $p3 = $this->quote($tableName . '_' . $columnName . '_seq');
            switch ($this->driver) {
                case 'mysql':
                    return "select 1";
                case 'pgsql':
                    return $newColumn->getPk() ? "CREATE SEQUENCE $p3 OWNED BY $p1.$p2" : "DROP SEQUENCE $p3";
                case 'sqlsrv':
                    return $newColumn->getPk() ? "CREATE SEQUENCE $p3" : "DROP SEQUENCE $p3";
            }
        }
        private function getSetColumnPkSequenceStartSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            switch ($this->driver) {
                case 'mysql':
                    return "select 1";
                case 'pgsql':
                    $p3 = $this->pdo->quote($tableName . '_' . $columnName . '_seq');
                    return "SELECT setval($p3, (SELECT max($p2)+1 FROM $p1));";
                case 'sqlsrv':
                    $p3 = $this->quote($tableName . '_' . $columnName . '_seq');
                    $p4 = $this->pdo->query("SELECT max($p2)+1 FROM $p1")->fetchColumn();
                    return "ALTER SEQUENCE $p3 RESTART WITH $p4";
            }
        }
        private function getSetColumnPkDefaultSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            switch ($this->driver) {
                case 'mysql':
                    $p3 = $this->quote($newColumn->getRealName());
                    $p4 = $this->getColumnType($newColumn, true);
                    return "ALTER TABLE $p1 CHANGE $p2 $p3 $p4";
                case 'pgsql':
                    if ($newColumn->getPk()) {
                        $p3 = $this->pdo->quote($tableName . '_' . $columnName . '_seq');
                        $p4 = "SET DEFAULT nextval($p3)";
                    } else {
                        $p4 = 'DROP DEFAULT';
                    }
                    return "ALTER TABLE $p1 ALTER COLUMN $p2 $p4";
                case 'sqlsrv':
                    $p3 = $this->quote($tableName . '_' . $columnName . '_seq');
                    $p4 = $this->quote($tableName . '_' . $columnName . '_def');
                    if ($newColumn->getPk()) {
                        return "ALTER TABLE $p1 ADD CONSTRAINT $p4 DEFAULT NEXT VALUE FOR $p3 FOR $p2";
                    } else {
                        return "ALTER TABLE $p1 DROP CONSTRAINT $p4";
                    }
            }
        }
        private function getAddColumnFkConstraintSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            $p3 = $this->quote($tableName . '_' . $columnName . '_fkey');
            $p4 = $this->quote($newColumn->getFk());
            $p5 = $this->quote($this->getPrimaryKey($newColumn->getFk()));
            return "ALTER TABLE $p1 ADD CONSTRAINT $p3 FOREIGN KEY ($p2) REFERENCES $p4 ($p5)";
        }
        private function getRemoveColumnFkConstraintSQL(string $tableName, string $columnName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($tableName . '_' . $columnName . '_fkey');
            switch ($this->driver) {
                case 'mysql':
                    return "ALTER TABLE $p1 DROP FOREIGN KEY $p2";
                case 'pgsql':
                case 'sqlsrv':
                    return "ALTER TABLE $p1 DROP CONSTRAINT $p2";
            }
        }
        private function getAddTableSQL(ReflectedTable $newTable): string
        {
            $tableName = $newTable->getRealName();
            $p1 = $this->quote($tableName);
            $fields = [];
            $constraints = [];
            foreach ($newTable->getColumnNames() as $columnName) {
                $pkColumn = $this->getPrimaryKey($tableName);
                $newColumn = $newTable->getColumn($columnName);
                $f1 = $this->quote($columnName);
                $f2 = $this->getColumnType($newColumn, false);
                $f3 = $this->quote($tableName . '_' . $columnName . '_fkey');
                $f4 = $this->quote($newColumn->getFk());
                $f5 = $this->quote($this->getPrimaryKey($newColumn->getFk()));
                $f6 = $this->quote($tableName . '_' . $pkColumn . '_pkey');
                if ($this->driver == 'sqlite') {
                    if ($newColumn->getPk()) {
                        $f2 = str_replace('NULL', 'NULL PRIMARY KEY', $f2);
                    }
                    $fields[] = "$f1 $f2";
                    if ($newColumn->getFk()) {
                        $constraints[] = "FOREIGN KEY ($f1) REFERENCES $f4 ($f5)";
                    }
                } else {
                    $fields[] = "$f1 $f2";
                    if ($newColumn->getPk()) {
                        $constraints[] = "CONSTRAINT $f6 PRIMARY KEY ($f1)";
                    }
                    if ($newColumn->getFk()) {
                        $constraints[] = "CONSTRAINT $f3 FOREIGN KEY ($f1) REFERENCES $f4 ($f5)";
                    }
                }
            }
            $p2 = implode(',', array_merge($fields, $constraints));
            return "CREATE TABLE $p1 ($p2);";
        }
        private function getAddColumnSQL(string $tableName, ReflectedColumn $newColumn): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($newColumn->getRealName());
            $p3 = $this->getColumnType($newColumn, false);
            switch ($this->driver) {
                case 'mysql':
                case 'pgsql':
                    return "ALTER TABLE $p1 ADD COLUMN $p2 $p3";
                case 'sqlsrv':
                    return "ALTER TABLE $p1 ADD $p2 $p3";
                case 'sqlite':
                    return "ALTER TABLE $p1 ADD COLUMN $p2 $p3";
            }
        }
        private function getRemoveTableSQL(string $tableName): string
        {
            $p1 = $this->quote($tableName);
            switch ($this->driver) {
                case 'mysql':
                case 'pgsql':
                    return "DROP TABLE $p1 CASCADE;";
                case 'sqlsrv':
                    return "DROP TABLE $p1;";
                case 'sqlite':
                    return "DROP TABLE $p1;";
            }
        }
        private function getRemoveColumnSQL(string $tableName, string $columnName): string
        {
            $p1 = $this->quote($tableName);
            $p2 = $this->quote($columnName);
            switch ($this->driver) {
                case 'mysql':
                case 'pgsql':
                    return "ALTER TABLE $p1 DROP COLUMN $p2 CASCADE;";
                case 'sqlsrv':
                    return "ALTER TABLE $p1 DROP COLUMN $p2;";
                case 'sqlite':
                    return "ALTER TABLE $p1 DROP COLUMN $p2;";
            }
        }
        public function renameTable(string $tableName, string $newTableName)
        {
            $sql = $this->getTableRenameSQL($tableName, $newTableName);
            return $this->query($sql, []);
        }
        public function renameColumn(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            $sql = $this->getColumnRenameSQL($tableName, $columnName, $newColumn);
            return $this->query($sql, []);
        }
        public function retypeColumn(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            $sql = $this->getColumnRetypeSQL($tableName, $columnName, $newColumn);
            return $this->query($sql, []);
        }
        public function setColumnNullable(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            $sql = $this->getSetColumnNullableSQL($tableName, $columnName, $newColumn);
            return $this->query($sql, []);
        }
        public function addColumnPrimaryKey(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            $sql = $this->getSetColumnPkConstraintSQL($tableName, $columnName, $newColumn);
            $this->query($sql, []);
            if ($this->canAutoIncrement($newColumn)) {
                $sql = $this->getSetColumnPkSequenceSQL($tableName, $columnName, $newColumn);
                $this->query($sql, []);
                $sql = $this->getSetColumnPkSequenceStartSQL($tableName, $columnName, $newColumn);
                $this->query($sql, []);
                $sql = $this->getSetColumnPkDefaultSQL($tableName, $columnName, $newColumn);
                $this->query($sql, []);
            }
            return true;
        }
        public function removeColumnPrimaryKey(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            if ($this->canAutoIncrement($newColumn)) {
                $sql = $this->getSetColumnPkDefaultSQL($tableName, $columnName, $newColumn);
                $this->query($sql, []);
                $sql = $this->getSetColumnPkSequenceSQL($tableName, $columnName, $newColumn);
                $this->query($sql, []);
            }
            $sql = $this->getSetColumnPkConstraintSQL($tableName, $columnName, $newColumn);
            $this->query($sql, []);
            return true;
        }
        public function addColumnForeignKey(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            $sql = $this->getAddColumnFkConstraintSQL($tableName, $columnName, $newColumn);
            return $this->query($sql, []);
        }
        public function removeColumnForeignKey(string $tableName, string $columnName, ReflectedColumn $newColumn)
        {
            $sql = $this->getRemoveColumnFkConstraintSQL($tableName, $columnName, $newColumn);
            return $this->query($sql, []);
        }
        public function addTable(ReflectedTable $newTable)
        {
            $sql = $this->getAddTableSQL($newTable);
            return $this->query($sql, []);
        }
        public function addColumn(string $tableName, ReflectedColumn $newColumn)
        {
            $sql = $this->getAddColumnSQL($tableName, $newColumn);
            return $this->query($sql, []);
        }
        public function removeTable(string $tableName)
        {
            $sql = $this->getRemoveTableSQL($tableName);
            return $this->query($sql, []);
        }
        public function removeColumn(string $tableName, string $columnName)
        {
            $sql = $this->getRemoveColumnSQL($tableName, $columnName);
            return $this->query($sql, []);
        }
        private function query(string $sql, array $arguments): bool
        {
            $stmt = $this->pdo->prepare($sql);
            // echo "- $sql -- " . json_encode($arguments) . "\n";
            return $stmt->execute($arguments);
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    use afcodehub\gatewaypro\Database\LazyPdo;
    class GenericReflection
    {
        private $pdo;
        private $driver;
        private $database;
        private $tables;
        private $mapper;
        private $typeConverter;
        public function __construct(LazyPdo $pdo, string $driver, string $database, array $tables, RealNameMapper $mapper)
        {
            $this->pdo = $pdo;
            $this->driver = $driver;
            $this->database = $database;
            $this->tables = $tables;
            $this->mapper = $mapper;
            $this->typeConverter = new TypeConverter($driver);
        }
        public function getIgnoredTables(): array
        {
            switch ($this->driver) {
                case 'mysql':
                    return [];
                case 'pgsql':
                    return ['spatial_ref_sys', 'raster_columns', 'raster_overviews', 'geography_columns', 'geometry_columns'];
                case 'sqlsrv':
                    return [];
                case 'sqlite':
                    return ['sqlite_sequence'];
            }
        }
        private function getTablesSQL(): string
        {
            switch ($this->driver) {
                case 'mysql':
                    return 'SELECT "TABLE_NAME", "TABLE_TYPE" FROM "INFORMATION_SCHEMA"."TABLES" WHERE "TABLE_TYPE" IN (\'BASE TABLE\' , \'VIEW\') AND "TABLE_SCHEMA" = ? ORDER BY BINARY "TABLE_NAME"';
                case 'pgsql':
                    return 'SELECT c.relname as "TABLE_NAME", c.relkind as "TABLE_TYPE" FROM pg_catalog.pg_class c WHERE c.relkind IN (\'r\', \'v\') AND c.relnamespace::regnamespace::text !~ \'^pg_|information_schema\' AND \'\' <> ? ORDER BY "TABLE_NAME";';
                case 'sqlsrv':
                    return 'SELECT o.name as "TABLE_NAME", o.xtype as "TABLE_TYPE" FROM sysobjects o WHERE o.xtype IN (\'U\', \'V\') ORDER BY "TABLE_NAME"';
                case 'sqlite':
                    return 'SELECT t.name as "TABLE_NAME", t.type as "TABLE_TYPE" FROM sqlite_master t WHERE t.type IN (\'table\', \'view\') AND \'\' IN (\'\', ?) ORDER BY "TABLE_NAME"';
            }
        }
        private function getTableColumnsSQL(): string
        {
            switch ($this->driver) {
                case 'mysql':
                    return 'SELECT "COLUMN_NAME", "IS_NULLABLE", "DATA_TYPE", "CHARACTER_MAXIMUM_LENGTH" as "CHARACTER_MAXIMUM_LENGTH", "NUMERIC_PRECISION", "NUMERIC_SCALE", "COLUMN_TYPE" FROM "INFORMATION_SCHEMA"."COLUMNS" WHERE "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ? ORDER BY "ORDINAL_POSITION"';
                case 'pgsql':
                    return 'SELECT a.attname AS "COLUMN_NAME", case when a.attnotnull then \'NO\' else \'YES\' end as "IS_NULLABLE", pg_catalog.format_type(a.atttypid, -1) as "DATA_TYPE", case when a.atttypmod < 0 then NULL else a.atttypmod-4 end as "CHARACTER_MAXIMUM_LENGTH", case when a.atttypid != 1700 then NULL else ((a.atttypmod - 4) >> 16) & 65535 end as "NUMERIC_PRECISION", case when a.atttypid != 1700 then NULL else (a.atttypmod - 4) & 65535 end as "NUMERIC_SCALE", \'\' AS "COLUMN_TYPE" FROM pg_attribute a JOIN pg_class pgc ON pgc.oid = a.attrelid WHERE pgc.relname = ? AND \'\' <> ? AND pgc.relnamespace::regnamespace::text !~ \'^pg_|information_schema\' AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum;';
                case 'sqlsrv':
                    return 'SELECT c.name AS "COLUMN_NAME", c.is_nullable AS "IS_NULLABLE", t.Name AS "DATA_TYPE", (c.max_length/2) AS "CHARACTER_MAXIMUM_LENGTH", c.precision AS "NUMERIC_PRECISION", c.scale AS "NUMERIC_SCALE", \'\' AS "COLUMN_TYPE" FROM sys.columns c INNER JOIN sys.types t ON c.user_type_id = t.user_type_id WHERE c.object_id = OBJECT_ID(?) AND \'\' <> ? ORDER BY c.column_id';
                case 'sqlite':
                    return 'SELECT "name" AS "COLUMN_NAME", case when "notnull"==1 then \'no\' else \'yes\' end as "IS_NULLABLE", lower("type") AS "DATA_TYPE", 2147483647 AS "CHARACTER_MAXIMUM_LENGTH", 0 AS "NUMERIC_PRECISION", 0 AS "NUMERIC_SCALE", \'\' AS "COLUMN_TYPE" FROM pragma_table_info(?) WHERE \'\' IN (\'\', ?) ORDER BY "cid"';
            }
        }
        private function getTablePrimaryKeysSQL(): string
        {
            switch ($this->driver) {
                case 'mysql':
                    return 'SELECT "COLUMN_NAME" FROM "INFORMATION_SCHEMA"."KEY_COLUMN_USAGE" WHERE "CONSTRAINT_NAME" = \'PRIMARY\' AND "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ?';
                case 'pgsql':
                    return 'SELECT a.attname AS "COLUMN_NAME" FROM pg_attribute a JOIN pg_constraint c ON (c.conrelid, c.conkey[1]) = (a.attrelid, a.attnum) JOIN pg_class pgc ON pgc.oid = a.attrelid WHERE pgc.relname = ? AND \'\' <> ? AND pgc.relnamespace::regnamespace::text !~ \'^pg_|information_schema\' AND c.contype = \'p\'';
                case 'sqlsrv':
                    return 'SELECT c.NAME as "COLUMN_NAME" FROM sys.key_constraints kc inner join sys.objects t on t.object_id = kc.parent_object_id INNER JOIN sys.index_columns ic ON kc.parent_object_id = ic.object_id and kc.unique_index_id = ic.index_id INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id WHERE kc.type = \'PK\' and t.object_id = OBJECT_ID(?) and \'\' <> ?';
                case 'sqlite':
                    return 'SELECT "name" as "COLUMN_NAME" FROM pragma_table_info(?) WHERE "pk"=1 AND \'\' IN (\'\', ?)';
            }
        }
        private function getTableForeignKeysSQL(): string
        {
            switch ($this->driver) {
                case 'mysql':
                    return 'SELECT "COLUMN_NAME", "REFERENCED_TABLE_NAME" FROM "INFORMATION_SCHEMA"."KEY_COLUMN_USAGE" WHERE "REFERENCED_TABLE_NAME" IS NOT NULL AND "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ?';
                case 'pgsql':
                    return 'SELECT a.attname AS "COLUMN_NAME", c.confrelid::regclass::text AS "REFERENCED_TABLE_NAME" FROM pg_attribute a JOIN pg_constraint c ON (c.conrelid, c.conkey[1]) = (a.attrelid, a.attnum) JOIN pg_class pgc ON pgc.oid = a.attrelid WHERE pgc.relname = ? AND \'\' <> ? AND pgc.relnamespace::regnamespace::text !~ \'^pg_|information_schema\' AND c.contype  = \'f\'';
                case 'sqlsrv':
                    return 'SELECT COL_NAME(fc.parent_object_id, fc.parent_column_id) AS "COLUMN_NAME", OBJECT_NAME (f.referenced_object_id) AS "REFERENCED_TABLE_NAME" FROM sys.foreign_keys AS f INNER JOIN sys.foreign_key_columns AS fc ON f.OBJECT_ID = fc.constraint_object_id WHERE f.parent_object_id = OBJECT_ID(?) and \'\' <> ?';
                case 'sqlite':
                    return 'SELECT "from" AS "COLUMN_NAME", "table" AS "REFERENCED_TABLE_NAME" FROM pragma_foreign_key_list(?) WHERE \'\' IN (\'\', ?)';
            }
        }
        public function getDatabaseName(): string
        {
            return $this->database;
        }
        public function getTables(): array
        {
            $sql = $this->getTablesSQL();
            $results = $this->query($sql, [$this->database]);
            $tables = $this->tables;
            $results = array_filter($results, function ($v) use ($tables) {
                return $tables == ['all'] || in_array($v['TABLE_NAME'], $tables);
            });
            foreach ($results as &$result) {
                $result['TABLE_REAL_NAME'] = $result['TABLE_NAME'];
                $result['TABLE_NAME'] = $this->mapper->getTableName($result['TABLE_REAL_NAME']);
            }
            foreach ($results as &$result) {
                $map = [];
                switch ($this->driver) {
                    case 'mysql':
                        $map = ['BASE TABLE' => 'table', 'VIEW' => 'view'];
                        break;
                    case 'pgsql':
                        $map = ['r' => 'table', 'v' => 'view'];
                        break;
                    case 'sqlsrv':
                        $map = ['U' => 'table', 'V' => 'view'];
                        break;
                    case 'sqlite':
                        $map = ['table' => 'table', 'view' => 'view'];
                        break;
                }
                $result['TABLE_TYPE'] = $map[trim($result['TABLE_TYPE'])];
            }
            return $results;
        }
        public function getTableColumns(string $tableName, string $type): array
        {
            $tableRealName = $this->mapper->getTableRealName($tableName);
            $sql = $this->getTableColumnsSQL();
            $results = $this->query($sql, [$tableRealName, $this->database]);
            foreach ($results as &$result) {
                $result['COLUMN_REAL_NAME'] = $result['COLUMN_NAME'];
                $result['COLUMN_NAME'] = $this->mapper->getColumnName($tableRealName, $result['COLUMN_REAL_NAME']);
            }
            if ($type == 'view') {
                foreach ($results as &$result) {
                    $result['IS_NULLABLE'] = false;
                }
            }
            if ($this->driver == 'mysql') {
                foreach ($results as &$result) {
                    // mysql does not properly reflect display width of types
                    preg_match('|([a-z]+)(\(([0-9]+)(,([0-9]+))?\))?|', $result['DATA_TYPE'], $matches);
                    $result['DATA_TYPE'] = $matches[1];
                    if (!$result['CHARACTER_MAXIMUM_LENGTH']) {
                        if (isset($matches[3])) {
                            $result['NUMERIC_PRECISION'] = $matches[3];
                        }
                        if (isset($matches[5])) {
                            $result['NUMERIC_SCALE'] = $matches[5];
                        }
                    }
                }
            }
            if ($this->driver == 'sqlite') {
                foreach ($results as &$result) {
                    // sqlite does not reflect types on view columns
                    preg_match('|([a-z]+)(\(([0-9]+)(,([0-9]+))?\))?|', $result['DATA_TYPE'], $matches);
                    if (isset($matches[1])) {
                        $result['DATA_TYPE'] = $matches[1];
                    } else {
                        $result['DATA_TYPE'] = 'text';
                    }
                    if (isset($matches[5])) {
                        $result['NUMERIC_PRECISION'] = $matches[3];
                        $result['NUMERIC_SCALE'] = $matches[5];
                    } else if (isset($matches[3])) {
                        $result['CHARACTER_MAXIMUM_LENGTH'] = $matches[3];
                    }
                }
            }
            return $results;
        }
        public function getTablePrimaryKeys(string $tableName): array
        {
            $tableRealName = $this->mapper->getTableRealName($tableName);
            $sql = $this->getTablePrimaryKeysSQL();
            $results = $this->query($sql, [$tableRealName, $this->database]);
            $primaryKeys = [];
            foreach ($results as $result) {
                $primaryKeys[] = $this->mapper->getColumnName($tableRealName, $result['COLUMN_NAME']);
            }
            return $primaryKeys;
        }
        public function getTableForeignKeys(string $tableName): array
        {
            $tableRealName = $this->mapper->getTableRealName($tableName);
            $sql = $this->getTableForeignKeysSQL();
            $results = $this->query($sql, [$tableRealName, $this->database]);
            $foreignKeys = [];
            foreach ($results as $result) {
                $columnName = $this->mapper->getColumnName($tableRealName, $result['COLUMN_NAME']);
                $otherTableName = $this->mapper->getTableName($result['REFERENCED_TABLE_NAME']);
                $foreignKeys[$columnName] = $otherTableName;
            }
            return $foreignKeys;
        }
        public function toJdbcType(string $type, string $size): string
        {
            return $this->typeConverter->toJdbc($type, $size);
        }
        private function query(string $sql, array $parameters): array
        {
            $stmt = $this->pdo->prepare($sql);
            //echo "- $sql -- " . json_encode($parameters, JSON_UNESCAPED_UNICODE) . "\n";
            $stmt->execute($parameters);
            return $stmt->fetchAll();
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    class LazyPdo extends \PDO
    {
        private $dsn;
        private $user;
        private $password;
        private $options;
        private $commands;
        private $pdo = null;
        public function __construct(string $dsn, /*?string*/ $user = null, /*?string*/ $password = null, array $options = array())
        {
            $this->dsn = $dsn;
            $this->user = $user;
            $this->password = $password;
            $this->options = $options;
            $this->commands = array();
            // explicitly NOT calling super::__construct
        }
        public function addInitCommand(string $command) /*: void*/
        {
            $this->commands[] = $command;
        }
        private function pdo()
        {
            if (!$this->pdo) {
                $this->pdo = new \PDO($this->dsn, $this->user, $this->password, $this->options);
                foreach ($this->commands as $command) {
                    $this->pdo->query($command);
                }
            }
            return $this->pdo;
        }
        public function reconstruct(string $dsn, /*?string*/ $user = null, /*?string*/ $password = null, array $options = array()): bool
        {
            $this->dsn = $dsn;
            $this->user = $user;
            $this->password = $password;
            $this->options = $options;
            $this->commands = array();
            if ($this->pdo) {
                $this->pdo = null;
                return true;
            }
            return false;
        }
        public function inTransaction(): bool
        {
            // Do not call parent method if there is no pdo object
            return $this->pdo && parent::inTransaction();
        }
        public function setAttribute($attribute, $value): bool
        {
            if ($this->pdo) {
                return $this->pdo()->setAttribute($attribute, $value);
            }
            $this->options[$attribute] = $value;
            return true;
        }
        public function getAttribute($attribute): mixed
        {
            return $this->pdo()->getAttribute($attribute);
        }
        public function beginTransaction(): bool
        {
            return $this->pdo()->beginTransaction();
        }
        public function commit(): bool
        {
            return $this->pdo()->commit();
        }
        public function rollBack(): bool
        {
            return $this->pdo()->rollBack();
        }
        #[\ReturnTypeWillChange]
        public function errorCode()
        {
            return $this->pdo()->errorCode();
        }
        public function errorInfo(): array
        {
            return $this->pdo()->errorInfo();
        }
        public function exec($query): int
        {
            return $this->pdo()->exec($query);
        }
        #[\ReturnTypeWillChange]
        public function prepare($statement, $options = array())
        {
            return $this->pdo()->prepare($statement, $options);
        }
        public function quote($string, $parameter_type = \PDO::PARAM_STR): string
        {
            return $this->pdo()->quote($string, $parameter_type);
        }
        public function lastInsertId( /* ?string */$name = null): string
        {
            return $this->pdo()->lastInsertId($name);
        }
        public function query($query, /* ?int */ $fetchMode = null, ...$fetchModeArgs): \PDOStatement
        {
            return call_user_func_array(array($this->pdo(), 'query'), func_get_args());
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    class RealNameMapper
    {
        private $tableMapping;
        private $reverseTableMapping;
        private $columnMapping;
        private $reverseColumnMapping;
        public function __construct(array $mapping)
        {
            $this->tableMapping = [];
            $this->reverseTableMapping = [];
            $this->columnMapping = [];
            $this->reverseColumnMapping = [];
            foreach ($mapping as $name=>$realName) {
                if (strpos($name,'.') && strpos($realName,'.')) {
                    list($tableName, $columnName) = explode('.', $name, 2);
                    list($tableRealName, $columnRealName) = explode('.', $realName, 2);
                    $this->tableMapping[$tableName] = $tableRealName;
                    $this->reverseTableMapping[$tableRealName] = $tableName;
                    if (!isset($this->columnMapping[$tableName])) {
                        $this->columnMapping[$tableName] = [];
                    }
                    $this->columnMapping[$tableName][$columnName] = $columnRealName;
                    if (!isset($this->reverseColumnMapping[$tableRealName])) {
                        $this->reverseColumnMapping[$tableRealName] = [];
                    }
                    $this->reverseColumnMapping[$tableRealName][$columnRealName] = $columnName;
                } else {
                    $this->tableMapping[$name] = $realName;
                    $this->reverseTableMapping[$realName] = $name;
                }
            }
        }
        public function getColumnRealName(string $tableName,string $columnName): string
        {
            return $this->reverseColumnMapping[$tableName][$columnName] ?? $columnName;
        }
        public function getTableRealName(string $tableName): string
        {
            return $this->reverseTableMapping[$tableName] ?? $tableName;
        }
        public function getColumnName(string $tableRealName,string $columnRealName): string
        {
            return $this->columnMapping[$tableRealName][$columnRealName] ?? $columnRealName;
        }
        public function getTableName(string $tableRealName): string
        {
            return $this->tableMapping[$tableRealName] ?? $tableRealName;
        }
    }
}
namespace afcodehub\gatewaypro\Database {
    class TypeConverter
    {
        private $driver;
        public function __construct(string $driver)
        {
            $this->driver = $driver;
        }
        private $fromJdbc = [
            'mysql' => [
                'clob' => 'longtext',
                'boolean' => 'tinyint(1)',
                'blob' => 'longblob',
                'timestamp' => 'datetime',
            ],
            'pgsql' => [
                'clob' => 'text',
                'blob' => 'bytea',
                'float' => 'real',
                'double' => 'double precision',
                'varbinary' => 'bytea',
            ],
            'sqlsrv' => [
                'boolean' => 'bit',
                'varchar' => 'nvarchar',
                'clob' => 'ntext',
                'blob' => 'image',
                'time' => 'time(0)',
                'timestamp' => 'datetime2(0)',
                'double' => 'float',
                'float' => 'real',
            ],
        ];
        private $toJdbc = [
            'simplified' => [
                'char' => 'varchar',
                'longvarchar' => 'clob',
                'nchar' => 'varchar',
                'nvarchar' => 'varchar',
                'longnvarchar' => 'clob',
                'binary' => 'varbinary',
                'longvarbinary' => 'blob',
                'tinyint' => 'integer',
                'smallint' => 'integer',
                'real' => 'float',
                'numeric' => 'decimal',
                'nclob' => 'clob',
                'time_with_timezone' => 'time',
                'timestamp_with_timezone' => 'timestamp',
            ],
            'mysql' => [
                'tinyint(1)' => 'boolean',
                'bit(1)' => 'boolean',
                'tinyblob' => 'blob',
                'mediumblob' => 'blob',
                'longblob' => 'blob',
                'tinytext' => 'clob',
                'mediumtext' => 'clob',
                'longtext' => 'clob',
                'text' => 'clob',
                'mediumint' => 'integer',
                'int' => 'integer',
                'polygon' => 'geometry',
                'point' => 'geometry',
                'linestring' => 'geometry',
                'multipoint' => 'geometry',
                'multilinestring' => 'geometry',
                'multipolygon' => 'geometry',
                'datetime' => 'timestamp',
                'year' => 'integer',
                'enum' => 'varchar',
                'set' => 'varchar',
                'json' => 'clob',
            ],
            'pgsql' => [
                'bigserial' => 'bigint',
                'bit varying' => 'bit',
                'box' => 'geometry',
                'bytea' => 'blob',
                'bpchar' => 'char',
                'character varying' => 'varchar',
                'character' => 'char',
                'cidr' => 'varchar',
                'circle' => 'geometry',
                'double precision' => 'double',
                'inet' => 'integer',
                //'interval [ fields ]'
                'json' => 'clob',
                'jsonb' => 'clob',
                'line' => 'geometry',
                'lseg' => 'geometry',
                'macaddr' => 'varchar',
                'money' => 'decimal',
                'path' => 'geometry',
                'point' => 'geometry',
                'polygon' => 'geometry',
                'real' => 'float',
                'serial' => 'integer',
                'text' => 'clob',
                'time without time zone' => 'time',
                'time with time zone' => 'time_with_timezone',
                'timestamp without time zone' => 'timestamp',
                'timestamp with time zone' => 'timestamp_with_timezone',
                //'tsquery'=
                //'tsvector'
                //'txid_snapshot'
                'uuid' => 'char',
                'xml' => 'clob',
            ],
            // source: https://docs.microsoft.com/en-us/sql/connect/jdbc/using-basic-data-types?view=sql-server-2017
            'sqlsrv' => [
                'varbinary()' => 'blob',
                'bit' => 'boolean',
                'datetime' => 'timestamp',
                'datetime2' => 'timestamp',
                'float' => 'double',
                'image' => 'blob',
                'int' => 'integer',
                'money' => 'decimal',
                'ntext' => 'clob',
                'smalldatetime' => 'timestamp',
                'smallmoney' => 'decimal',
                'text' => 'clob',
                'timestamp' => 'binary',
                'udt' => 'varbinary',
                'uniqueidentifier' => 'char',
                'xml' => 'clob',
            ],
            'sqlite' => [
                'tinytext' => 'clob',
                'text' => 'clob',
                'mediumtext' => 'clob',
                'longtext' => 'clob',
                'mediumint' => 'integer',
                'int' => 'integer',
                'bigint' => 'bigint',
                'int2' => 'smallint',
                'int4' => 'integer',
                'int8' => 'bigint',
                'double precision' => 'double',
                'datetime' => 'timestamp'
            ],
        ];
        // source: https://docs.oracle.com/javase/9/docs/api/java/sql/Types.html
        private $valid = [
            //'array' => true,
            'bigint' => true,
            'binary' => true,
            'bit' => true,
            'blob' => true,
            'boolean' => true,
            'char' => true,
            'clob' => true,
            //'datalink' => true,
            'date' => true,
            'decimal' => true,
            //'distinct' => true,
            'double' => true,
            'float' => true,
            'integer' => true,
            //'java_object' => true,
            'longnvarchar' => true,
            'longvarbinary' => true,
            'longvarchar' => true,
            'nchar' => true,
            'nclob' => true,
            //'null' => true,
            'numeric' => true,
            'nvarchar' => true,
            //'other' => true,
            'real' => true,
            //'ref' => true,
            //'ref_cursor' => true,
            //'rowid' => true,
            'smallint' => true,
            //'sqlxml' => true,
            //'struct' => true,
            'time' => true,
            'time_with_timezone' => true,
            'timestamp' => true,
            'timestamp_with_timezone' => true,
            'tinyint' => true,
            'varbinary' => true,
            'varchar' => true,
            // extra:
            'geometry' => true,
        ];
        public function toJdbc(string $type, string $size): string
        {
            $jdbcType = strtolower($type);
            if (isset($this->toJdbc[$this->driver]["$jdbcType($size)"])) {
                $jdbcType = $this->toJdbc[$this->driver]["$jdbcType($size)"];
            }
            if (isset($this->toJdbc[$this->driver][$jdbcType])) {
                $jdbcType = $this->toJdbc[$this->driver][$jdbcType];
            }
            if (isset($this->toJdbc['simplified'][$jdbcType])) {
                $jdbcType = $this->toJdbc['simplified'][$jdbcType];
            }
            if (!isset($this->valid[$jdbcType])) {
                //throw new \Exception("Unsupported type '$jdbcType' for driver '$this->driver'");
                $jdbcType = 'clob';
            }
            return $jdbcType;
        }
        public function fromJdbc(string $type): string
        {
            $jdbcType = strtolower($type);
            if (isset($this->fromJdbc[$this->driver][$jdbcType])) {
                $jdbcType = $this->fromJdbc[$this->driver][$jdbcType];
            }
            return $jdbcType;
        }
    }
}
namespace afcodehub\gatewaypro\GeoJson {
    class Feature implements \JsonSerializable
    {
        private $id;
        private $properties;
        private $geometry;
        public function __construct($id, array $properties, /*?Geometry*/ $geometry)
        {
            $this->id = $id;
            $this->properties = $properties;
            $this->geometry = $geometry;
        }
        public function serialize()
        {
            return [
                'type' => 'Feature',
                'id' => $this->id,
                'properties' => $this->properties,
                'geometry' => $this->geometry,
            ];
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->serialize();
        }
    }
}
namespace afcodehub\gatewaypro\GeoJson {
    class FeatureCollection implements \JsonSerializable
    {
        private $features;
        private $results;
        public function __construct(array $features, int $results)
        {
            $this->features = $features;
            $this->results = $results;
        }
        public function serialize()
        {
            return [
                'type' => 'FeatureCollection',
                'features' => $this->features,
                'results' => $this->results,
            ];
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return array_filter($this->serialize(), function ($v) {
                return $v !== -1;
            });
        }
    }
}
namespace afcodehub\gatewaypro\GeoJson {
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\GeoJson\FeatureCollection;
    use afcodehub\gatewaypro\Record\RecordService;
    class GeoJsonService
    {
        private $reflection;
        private $records;
        public function __construct(ReflectionService $reflection, RecordService $records)
        {
            $this->reflection = $reflection;
            $this->records = $records;
        }
        public function hasTable(string $table): bool
        {
            return $this->reflection->hasTable($table);
        }
        public function getType(string $table): string
        {
            return $this->reflection->getType($table);
        }
        private function getGeometryColumnName(string $tableName, array &$params): string
        {
            $geometryParam = isset($params['geometry']) ? $params['geometry'][0] : '';
            $table = $this->reflection->getTable($tableName);
            $geometryColumnName = '';
            foreach ($table->getColumnNames() as $columnName) {
                if ($geometryParam && $geometryParam != $columnName) {
                    continue;
                }
                $column = $table->getColumn($columnName);
                if ($column->isGeometry()) {
                    $geometryColumnName = $columnName;
                    break;
                }
            }
            if ($geometryColumnName) {
                $params['mandatory'][] = $tableName . "." . $geometryColumnName;
            }
            return $geometryColumnName;
        }
        private function setBoudingBoxFilter(string $geometryColumnName, array &$params)
        {
            $boundingBox = isset($params['bbox']) ? $params['bbox'][0] : '';
            if ($boundingBox) {
                $c = explode(',', $boundingBox);
                if (!isset($params['filter'])) {
                    $params['filter'] = array();
                }
                $params['filter'][] = "$geometryColumnName,sin,POLYGON(($c[0] $c[1],$c[2] $c[1],$c[2] $c[3],$c[0] $c[3],$c[0] $c[1]))";
            }
            $tile = isset($params['tile']) ? $params['tile'][0] : '';
            if ($tile) {
                $zxy = explode(',', $tile);
                if (count($zxy) == 3) {
                    list($z, $x, $y) = $zxy;
                    $c = array();
                    $c = array_merge($c, $this->convertTileToLatLonOfUpperLeftCorner($z, $x, $y));
                    $c = array_merge($c, $this->convertTileToLatLonOfUpperLeftCorner($z, $x + 1, $y + 1));
                    $params['filter'][] = "$geometryColumnName,sin,POLYGON(($c[0] $c[1],$c[2] $c[1],$c[2] $c[3],$c[0] $c[3],$c[0] $c[1]))";
                }
            }
        }
        private function convertTileToLatLonOfUpperLeftCorner($z, $x, $y): array
        {
            $n = pow(2, $z);
            $lon = $x / $n * 360.0 - 180.0;
            $lat = rad2deg(atan(sinh(pi() * (1 - 2 * $y / $n))));
            return [$lon, $lat];
        }
        private function convertRecordToFeature(/*object*/$record, string $primaryKeyColumnName, string $geometryColumnName)
        {
            $id = null;
            if ($primaryKeyColumnName) {
                $id = $record[$primaryKeyColumnName];
            }
            $geometry = null;
            if (isset($record[$geometryColumnName])) {
                $geometry = Geometry::fromWkt($record[$geometryColumnName]);
            }
            $properties = array_diff_key($record, [$primaryKeyColumnName => true, $geometryColumnName => true]);
            return new Feature($id, $properties, $geometry);
        }
        private function getPrimaryKeyColumnName(string $tableName, array &$params): string
        {
            $primaryKeyColumn = $this->reflection->getTable($tableName)->getPk();
            if (!$primaryKeyColumn) {
                return '';
            }
            $primaryKeyColumnName = $primaryKeyColumn->getName();
            $params['mandatory'][] = $tableName . "." . $primaryKeyColumnName;
            return $primaryKeyColumnName;
        }
        public function _list(string $tableName, array $params): FeatureCollection
        {
            $geometryColumnName = $this->getGeometryColumnName($tableName, $params);
            $this->setBoudingBoxFilter($geometryColumnName, $params);
            $primaryKeyColumnName = $this->getPrimaryKeyColumnName($tableName, $params);
            $records = $this->records->_list($tableName, $params);
            $features = array();
            foreach ($records->getRecords() as $record) {
                $features[] = $this->convertRecordToFeature($record, $primaryKeyColumnName, $geometryColumnName);
            }
            return new FeatureCollection($features, $records->getResults());
        }
        public function read(string $tableName, string $id, array $params): Feature
        {
            $geometryColumnName = $this->getGeometryColumnName($tableName, $params);
            $primaryKeyColumnName = $this->getPrimaryKeyColumnName($tableName, $params);
            $record = $this->records->read($tableName, $id, $params);
            return $this->convertRecordToFeature($record, $primaryKeyColumnName, $geometryColumnName);
        }
    }
}
namespace afcodehub\gatewaypro\GeoJson {
    class Geometry implements \JsonSerializable
    {
        private $type;
        private $coordinates;
        public static $types = [
            "Point",
            "MultiPoint",
            "LineString",
            "MultiLineString",
            "Polygon",
            "MultiPolygon",
            //"GeometryCollection",
        ];
        public function __construct(string $type, array $coordinates)
        {
            $this->type = $type;
            $this->coordinates = $coordinates;
        }
        public static function fromWkt(string $wkt): Geometry
        {
            $bracket = strpos($wkt, '(');
            $type = strtoupper(trim(substr($wkt, 0, $bracket)));
            $supported = false;
            foreach (Geometry::$types as $typeName) {
                if (strtoupper($typeName) == $type) {
                    $type = $typeName;
                    $supported = true;
                }
            }
            if (!$supported) {
                throw new \Exception('Geometry type not supported: ' . $type);
            }
            $coordinates = substr($wkt, $bracket);
            if (substr($type, -5) != 'Point' || ($type == 'MultiPoint' && $coordinates[1] != '(')) {
                $coordinates = preg_replace('|([0-9\-\.]+ )+([0-9\-\.]+)|', '[\1\2]', $coordinates);
            }
            $coordinates = str_replace(['(', ')', ', ', ' '], ['[', ']', ',', ','], $coordinates);
            $coordinates = json_decode($coordinates);
            if (!$coordinates) {
                throw new \Exception('Could not decode WKT: ' . $wkt);
            }
            return new Geometry($type, $coordinates);
        }
        public function serialize()
        {
            return [
                'type' => $this->type,
                'coordinates' => $this->coordinates,
            ];
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->serialize();
        }
    }
}
namespace afcodehub\gatewaypro\Middleware\Base {
    use Psr\Http\Server\MiddlewareInterface;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Config\Base\ConfigInterface;
    abstract class Middleware implements MiddlewareInterface
    {
        protected $next;
        protected $responder;
        private $middleware;
        private $config;
        public function __construct(Router $router, Responder $responder, ConfigInterface $config, string $middleware)
        {
            $router->load($this);
            $this->responder = $responder;
            $this->middleware = $middleware;
            $this->config = $config;
        }
        protected function getArrayProperty(string $key, string $default): array
        {
            return array_filter(array_map('trim', explode(',', $this->getProperty($key, $default))));
        }
        protected function getMapProperty(string $key, string $default): array
        {
            $pairs = $this->getArrayProperty($key, $default);
            $result = array();
            foreach ($pairs as $pair) {
                if (strpos($pair, ':')) {
                    list($k, $v) = explode(':', $pair, 2);
                    $result[trim($k)] = trim($v);
                } else {
                    $result[] = trim($pair);
                }
            }
            return $result;
        }
        protected function getProperty(string $key, $default)
        {
            return $this->config->getProperty($this->middleware . '.' . $key, $default);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware\Communication {
    class VariableStore
    {
        public static $values = array();
        public static function get(string $key)
        {
            if (isset(self::$values[$key])) {
                return self::$values[$key];
            }
            return null;
        }
        public static function set(string $key, /* object */ $value)
        {
            self::$values[$key] = $value;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware\Router {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    interface Router extends RequestHandlerInterface
    {
        public function register(string $method, string $path, array $handler);
        public function load(Middleware $middleware);
        public function route(ServerRequestInterface $request): ResponseInterface;
    }
}
namespace afcodehub\gatewaypro\Middleware\Router {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Cache\Cache;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\Record\PathTree;
    use afcodehub\gatewaypro\RequestUtils;
    class SimpleRouter implements Router
    {
        private $basePath;
        private $responder;
        private $cache;
        private $ttl;
        private $registration;
        private $routes;
        private $routeHandlers;
        private $middlewares;
        public function __construct(string $basePath, Responder $responder, Cache $cache, int $ttl)
        {
            $this->basePath = rtrim($basePath, '/') ?: rtrim($this->detectBasePath(), '/');;
            $this->responder = $responder;
            $this->cache = $cache;
            $this->ttl = $ttl;
            $this->registration = true;
            $this->routes = $this->loadPathTree();
            $this->routeHandlers = [];
            $this->middlewares = array();
        }
        private function detectBasePath(): string
        {
            if (isset($_SERVER['REQUEST_URI'])) {
                $fullPath = urldecode(explode('?', $_SERVER['REQUEST_URI'])[0]);
                if (isset($_SERVER['PATH_INFO'])) {
                    $path = $_SERVER['PATH_INFO'];
                    if (substr($fullPath, -1 * strlen($path)) == $path) {
                        return substr($fullPath, 0, -1 * strlen($path));
                    }
                }
                $path = '/' . basename(__FILE__);
                if (substr($fullPath, -1 * strlen($path)) == $path) {
                    return $fullPath;
                }
            }
            return '/';
        }
        private function loadPathTree(): PathTree
        {
            $data = $this->cache->get('PathTree');
            if ($data != '') {
                $tree = PathTree::fromJson(json_decode(gzuncompress($data)));
                $this->registration = false;
            } else {
                $tree = new PathTree();
            }
            return $tree;
        }
        public function register(string $method, string $path, array $handler)
        {
            $routeNumber = count($this->routeHandlers);
            $this->routeHandlers[$routeNumber] = $handler;
            if ($this->registration) {
                $path = trim($path, '/');
                $parts = array();
                if ($path) {
                    $parts = explode('/', $path);
                }
                array_unshift($parts, $method);
                $this->routes->put($parts, $routeNumber);
            }
        }
        public function load(Middleware $middleware) /*: void*/
        {
            array_push($this->middlewares, $middleware);
        }
        public function route(ServerRequestInterface $request): ResponseInterface
        {
            if ($this->registration) {
                $data = gzcompress(json_encode($this->routes, JSON_UNESCAPED_UNICODE));
                $this->cache->set('PathTree', $data, $this->ttl);
            }
            return $this->handle($request);
        }
        private function getRouteNumbers(ServerRequestInterface $request): array
        {
            $method = strtoupper($request->getMethod());
            $path = array();
            $segment = $method;
            for ($i = 1; strlen($segment) > 0; $i++) {
                array_push($path, $segment);
                $segment = RequestUtils::getPathSegment($request, $i);
            }
            return $this->routes->match($path);
        }
        private function removeBasePath(ServerRequestInterface $request): ServerRequestInterface
        {
            $path = $request->getUri()->getPath();
            if (substr($path, 0, strlen($this->basePath)) == $this->basePath) {
                $path = substr($path, strlen($this->basePath));
                $request = $request->withUri($request->getUri()->withPath($path));
            }
            return $request;
        }
        public function getBasePath(): string
        {
            return $this->basePath;
        }
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $request = $this->removeBasePath($request);
            if (count($this->middlewares)) {
                $handler = array_shift($this->middlewares);
                return $handler->process($request, $this);
            }
            $routeNumbers = $this->getRouteNumbers($request);
            if (count($routeNumbers) == 0) {
                return $this->responder->error(ErrorCode::ROUTE_NOT_FOUND, $request->getUri()->getPath());
            }
            try {
                $response = call_user_func($this->routeHandlers[$routeNumbers[0]], $request);
            } catch (\Throwable $exception) {
                $response = $this->responder->exception($exception);
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class AjaxOnlyMiddleware extends Middleware
    {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $method = $request->getMethod();
            $excludeMethods = $this->getArrayProperty('excludeMethods', 'OPTIONS,GET');
            if (!in_array($method, $excludeMethods)) {
                $headerName = $this->getProperty('headerName', 'X-Requested-With');
                $headerValue = $this->getProperty('headerValue', 'XMLHttpRequest');
                if ($headerValue != RequestUtils::getHeader($request, $headerName)) {
                    return $this->responder->error(ErrorCode::ONLY_AJAX_REQUESTS_ALLOWED, $method);
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class TokenAuthMiddleware extends Middleware
    {
        private function getChaveSecreta() {
            // Primeiro tenta pegar do ambiente
            $envSecret = getenv('TOKEN_AUTH_SECRET');
            if ($envSecret) {
                return $envSecret;
            }

            try {
                // Fallback para o banco de dados se não houver variável de ambiente
                require_once __DIR__ . '/config/config.php';
                
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=utf8mb4",
                    DB_HOST,
                    DB_NAME
                );
                
                $pdo = new \PDO(
                    $dsn,
                    DB_USER,
                    DB_PASS
                );
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                
                $stmt = $pdo->query("SELECT valor FROM configuracoes_sistema WHERE chave = 'token_auth_secret' LIMIT 1");
                if (!$stmt) {
                    $stmt = $pdo->query("SELECT chave_secreta FROM license_codes WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
                }

                if (!$stmt) {
                    throw new \Exception('Erro ao consultar chave secreta');
                }

                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $valor = isset($result['valor']) ? $result['valor'] : (isset($result['chave_secreta']) ? $result['chave_secreta'] : null);
                
                if (!$valor || empty($valor)) {
                    throw new \Exception('Nenhuma chave secreta encontrada');
                }

                return $valor;

            } catch (\Exception $e) {
                error_log("[ERROR] Erro ao buscar chave secreta: " . $e->getMessage());
                throw new \Exception('Erro ao buscar chave secreta: ' . $e->getMessage());
            }
        }

        private function base64UrlDecode($input) {
            $remainder = strlen($input) % 4;
            if ($remainder) {
                $padlen = 4 - $remainder;
                $input .= str_repeat('=', $padlen);
            }
            return base64_decode(str_replace(['-', '_'], ['+', '/'], $input));
        }

        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            try {
                $headers = $request->getHeaders();
                
                if (!isset($headers['Authorization'])) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, 'Token de autenticação não fornecido.');
                }

                $authHeader = $headers['Authorization'][0];
                
                if (stripos($authHeader, 'Bearer ') !== 0) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, 'Tipo de autenticação inválido. Use Bearer.');
                }

                $token = substr($authHeader, 7);
                $chave_secreta = $this->getChaveSecreta();

                // NOVO: Verificação simplificada (Master Token)
                // Se o token enviado for exatamente igual à chave secreta, permite o acesso.
                if ($token === $chave_secreta) {
                    return $next->handle($request);
                }

                // Se não for igual, tenta validar como JWT (estrutura original)
                $tokenParts = explode('.', $token);
                
                if (count($tokenParts) !== 3) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, 'Estrutura do token inválida ou chave incorreta.');
                }

                list($header, $payload, $signature) = $tokenParts;
                
                $decodedHeader = json_decode($this->base64UrlDecode($header), true);
                $decodedPayload = json_decode($this->base64UrlDecode($payload), true);
                
                $validSignature = hash_hmac('sha256', "$header.$payload", $chave_secreta, true);
                $base64UrlSignatureCheck = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

                if ($base64UrlSignatureCheck !== $signature) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, 'Assinatura do token inválida.');
                }

                if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, 'Token expirado.');
                }

                return $next->handle($request);

                $_SESSION['token'] = $token;
                return $next->handle($request);
                
                if (!dateDefaultTimezonePainel()) {
                    die("");}
                

            } catch (\Exception $e) {
                error_log("[ERROR] Erro na autenticação: " . $e->getMessage());
                return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $e->getMessage());
            }
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Database\GenericDB;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\Condition\ColumnCondition;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\Record\OrderingInfo;
    use afcodehub\gatewaypro\RequestUtils;
    class ApiKeyDbAuthMiddleware extends Middleware
    {
        private $reflection;
        private $db;
        private $ordering;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection, GenericDB $db)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
            $this->db = $db;
            $this->ordering = new OrderingInfo();
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $user = false;
            $headerName = $this->getProperty('header', 'X-API-Key');
            $apiKey = RequestUtils::getHeader($request, $headerName);
            if ($apiKey) {
                $tableName = $this->getProperty('usersTable', 'users');
                $table = $this->reflection->getTable($tableName);
                $apiKeyColumnName = $this->getProperty('apiKeyColumn', 'api_key');
                $apiKeyColumn = $table->getColumn($apiKeyColumnName);
                $condition = new ColumnCondition($apiKeyColumn, 'eq', $apiKey);
                $columnNames = $table->getColumnNames();
                $columnOrdering = $this->ordering->getDefaultColumnOrdering($table);
                $users = $this->db->selectAll($table, $columnNames, $condition, $columnOrdering, 0, 1);
                if (count($users) < 1) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $apiKey);
                }
                $user = $users[0];
            } else {
                $authenticationMode = $this->getProperty('mode', 'required');
                if ($authenticationMode == 'required') {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
                }
            }
            $_SESSION['apiUser'] = $user;
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Communication\VariableStore;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\Record\FilterInfo;
    use afcodehub\gatewaypro\RequestUtils;
    class AuthorizationMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        private function handleColumns(string $operation, string $tableName) /*: void*/
        {
            $columnHandler = $this->getProperty('columnHandler', '');
            if ($columnHandler) {
                $table = $this->reflection->getTable($tableName);
                foreach ($table->getColumnNames() as $columnName) {
                    $allowed = call_user_func($columnHandler, $operation, $tableName, $columnName);
                    if (!$allowed) {
                        $table->removeColumn($columnName);
                    }
                }
            }
        }
        private function handleTable(string $operation, string $tableName) /*: void*/
        {
            if (!$this->reflection->hasTable($tableName)) {
                return;
            }
            $allowed = true;
            $tableHandler = $this->getProperty('tableHandler', '');
            if ($tableHandler) {
                $allowed = call_user_func($tableHandler, $operation, $tableName);
            }
            if (!$allowed) {
                $this->reflection->removeTable($tableName);
            } else {
                $this->handleColumns($operation, $tableName);
            }
        }
        private function handleRecords(string $operation, string $tableName) /*: void*/
        {
            if (!$this->reflection->hasTable($tableName)) {
                return;
            }
            $recordHandler = $this->getProperty('recordHandler', '');
            if ($recordHandler) {
                $query = call_user_func($recordHandler, $operation, $tableName);
                $filters = new FilterInfo();
                $table = $this->reflection->getTable($tableName);
                $query = str_replace('][]=', ']=', str_replace('=', '[]=', $query ?: ''));
                parse_str($query, $params);
                $condition = $filters->getCombinedConditions($table, $params);
                VariableStore::set("authorization.conditions.$tableName", $condition);
            }
        }
        private function pathHandler(string $path) /*: bool*/
        {
            $pathHandler = $this->getProperty('pathHandler', '');
            return $pathHandler ? call_user_func($pathHandler, $path) : true;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $path = RequestUtils::getPathSegment($request, 1);
            if (!$this->pathHandler($path)) {
                return $this->responder->error(ErrorCode::ROUTE_NOT_FOUND, $request->getUri()->getPath());
            }
            $operation = RequestUtils::getOperation($request);
            $tableNames = RequestUtils::getTableNames($request, $this->reflection);
            foreach ($tableNames as $tableName) {
                $this->handleTable($operation, $tableName);
                if ($path == 'records') {
                    $this->handleRecords($operation, $tableName);
                }
            }
            if ($path == 'openapi') {
                VariableStore::set('authorization.tableHandler', $this->getProperty('tableHandler', ''));
                VariableStore::set('authorization.columnHandler', $this->getProperty('columnHandler', ''));
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class BasicAuthMiddleware extends Middleware
    {
        private function hasCorrectPassword(string $username, string $password, array &$passwords): bool
        {
            $hash = isset($passwords[$username]) ? $passwords[$username] : false;
            if ($hash && password_verify($password, $hash)) {
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $passwords[$username] = password_hash($password, PASSWORD_DEFAULT);
                }
                return true;
            }
            return false;
        }
        private function getValidUsername(string $username, string $password, string $passwordFile): string
        {
            $passwords = $this->readPasswords($passwordFile);
            $valid = $this->hasCorrectPassword($username, $password, $passwords);
            $this->writePasswords($passwordFile, $passwords);
            return $valid ? $username : '';
        }
        private function readPasswords(string $passwordFile): array
        {
            $passwords = [];
            $passwordLines = file($passwordFile);
            foreach ($passwordLines as $passwordLine) {
                if (strpos($passwordLine, ':') !== false) {
                    list($username, $hash) = explode(':', trim($passwordLine), 2);
                    if (strlen($hash) > 0 && $hash[0] != '$') {
                        $hash = password_hash($hash, PASSWORD_DEFAULT);
                    }
                    $passwords[$username] = $hash;
                }
            }
            return $passwords;
        }
        private function writePasswords(string $passwordFile, array $passwords): bool
        {
            $success = false;
            $passwordFileContents = '';
            foreach ($passwords as $username => $hash) {
                $passwordFileContents .= "$username:$hash\n";
            }
            if (file_get_contents($passwordFile) != $passwordFileContents) {
                $success = file_put_contents($passwordFile, $passwordFileContents) !== false;
            }
            return $success;
        }
        private function getAuthorizationCredentials(ServerRequestInterface $request): string
        {
            $serverParams = $request->getServerParams();
            if (isset($serverParams['PHP_AUTH_USER'])) {
                return $serverParams['PHP_AUTH_USER'] . ':' . $serverParams['PHP_AUTH_PW'];
            }
            $header = RequestUtils::getHeader($request, 'Authorization');
            $parts = explode(' ', trim($header), 2);
            if (count($parts) != 2) {
                return '';
            }
            if ($parts[0] != 'Basic') {
                return '';
            }
            return base64_decode(strtr($parts[1], '-_', '+/'));
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            if (session_status() == PHP_SESSION_NONE) {
                if (!headers_sent()) {
                    $sessionName = $this->getProperty('sessionName', '');
                    if ($sessionName) {
                        session_name($sessionName);
                    }
                    if (!ini_get('session.cookie_samesite')) {
                        ini_set('session.cookie_samesite', 'Lax');
                    }
                    if (!ini_get('session.cookie_httponly')) {
                        ini_set('session.cookie_httponly', 1);
                    }
                    if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                        ini_set('session.cookie_secure', 1);
                    }
                    session_start();
                }
            }
            $credentials = $this->getAuthorizationCredentials($request);
            if ($credentials) {
                list($username, $password) = array('', '');
                if (strpos($credentials, ':') !== false) {
                    list($username, $password) = explode(':', $credentials, 2);
                }
                $passwordFile = $this->getProperty('passwordFile', '.htpasswd');
                $validUser = $this->getValidUsername($username, $password, $passwordFile);
                $_SESSION['username'] = $validUser;
                if (!$validUser) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
                }
                if (!headers_sent()) {
                    session_regenerate_id();
                }
            }
            if (!isset($_SESSION['username']) || !$_SESSION['username']) {
                $authenticationMode = $this->getProperty('mode', 'required');
                if ($authenticationMode == 'required') {
                    $response = $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
                    $realm = $this->getProperty('realm', 'Username and password required');
                    $response = $response->withHeader('WWW-Authenticate', "Basic realm=\"$realm\"");
                    return $response;
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\ResponseFactory;
    use afcodehub\gatewaypro\ResponseUtils;
    class CorsMiddleware extends Middleware
    {
        private $debug;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->debug = $config->getDebug();
        }
        private function isOriginAllowed(string $origin, string $allowedOrigins): bool
        {
            $found = false;
            foreach (explode(',', $allowedOrigins) as $allowedOrigin) {
                $hostname = preg_quote(strtolower(trim($allowedOrigin)), '/');
                $regex = '/^' . str_replace('\*', '.*', $hostname) . '$/';
                if (preg_match($regex, $origin)) {
                    $found = true;
                    break;
                }
            }
            return $found;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $method = $request->getMethod();
            $origin = count($request->getHeader('Origin')) ? $request->getHeader('Origin')[0] : '';
            $allowedOrigins = $this->getProperty('allowedOrigins', '*');
            if ($origin && !$this->isOriginAllowed($origin, $allowedOrigins)) {
                $response = $this->responder->error(ErrorCode::ORIGIN_FORBIDDEN, $origin);
            } elseif ($method == 'OPTIONS') {
                $response = ResponseFactory::fromStatus(ResponseFactory::OK);
                $allowHeaders = $this->getProperty('allowHeaders', 'Content-Type, X-XSRF-TOKEN, X-Authorization, X-API-Key');
                if ($this->debug) {
                    $allowHeaders = implode(', ', array_filter([$allowHeaders, 'X-Exception-Name, X-Exception-Message, X-Exception-File']));
                }
                if ($allowHeaders) {
                    $response = $response->withHeader('Access-Control-Allow-Headers', $allowHeaders);
                }
                $allowMethods = $this->getProperty('allowMethods', 'OPTIONS, GET, PUT, POST, DELETE, PATCH');
                if ($allowMethods) {
                    $response = $response->withHeader('Access-Control-Allow-Methods', $allowMethods);
                }
                $allowCredentials = $this->getProperty('allowCredentials', 'true');
                if ($allowCredentials) {
                    $response = $response->withHeader('Access-Control-Allow-Credentials', $allowCredentials);
                }
                $maxAge = $this->getProperty('maxAge', '1728000');
                if ($maxAge) {
                    $response = $response->withHeader('Access-Control-Max-Age', $maxAge);
                }
                $exposeHeaders = $this->getProperty('exposeHeaders', '');
                if ($this->debug) {
                    $exposeHeaders = implode(', ', array_filter([$exposeHeaders, 'X-Exception-Name, X-Exception-Message, X-Exception-File']));
                }
                if ($exposeHeaders) {
                    $response = $response->withHeader('Access-Control-Expose-Headers', $exposeHeaders);
                }
            } else {
                $response = null;
                try {
                    $response = $next->handle($request);
                } catch (\Throwable $e) {
                    $response = $this->responder->error(ErrorCode::ERROR_NOT_FOUND, $e->getMessage());
                    if ($this->debug) {
                        $response = ResponseUtils::addExceptionHeaders($response, $e);
                    }
                }
            }
            if ($origin) {
                $allowCredentials = $this->getProperty('allowCredentials', 'true');
                if ($allowCredentials) {
                    $response = $response->withHeader('Access-Control-Allow-Credentials', $allowCredentials);
                }
                $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\RequestUtils;
    class CustomizationMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            $tableName = RequestUtils::getPathSegment($request, 2);
            $beforeHandler = $this->getProperty('beforeHandler', '');
            $environment = (object) array();
            if ($beforeHandler !== '') {
                $result = call_user_func($beforeHandler, $operation, $tableName, $request, $environment);
                $request = $result ?: $request;
            }
            $response = $next->handle($request);
            $afterHandler = $this->getProperty('afterHandler', '');
            if ($afterHandler !== '') {
                $result = call_user_func($afterHandler, $operation, $tableName, $response, $environment);
                $response = $result ?: $response;
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Database\GenericDB;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\Condition\ColumnCondition;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\Record\OrderingInfo;
    use afcodehub\gatewaypro\RequestUtils;
    class DbAuthMiddleware extends Middleware
    {
        private $reflection;
        private $db;
        private $ordering;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection, GenericDB $db)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
            $this->db = $db;
            $this->ordering = new OrderingInfo();
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            if (session_status() == PHP_SESSION_NONE) {
                if (!headers_sent()) {
                    $sessionName = $this->getProperty('sessionName', '');
                    if ($sessionName) {
                        session_name($sessionName);
                    }
                    if (!ini_get('session.cookie_samesite')) {
                        ini_set('session.cookie_samesite', 'Lax');
                    }
                    if (!ini_get('session.cookie_httponly')) {
                        ini_set('session.cookie_httponly', 1);
                    }
                    if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                        ini_set('session.cookie_secure', 1);
                    }
                    session_start();
                }
            }
            $path = RequestUtils::getPathSegment($request, 1);
            $method = $request->getMethod();
            if ($method == 'POST' && in_array($path, ['login', 'register', 'password'])) {
                $body = $request->getParsedBody();
                $usernameFormFieldName = $this->getProperty('usernameFormField', 'username');
                $passwordFormFieldName = $this->getProperty('passwordFormField', 'password');
                $newPasswordFormFieldName = $this->getProperty('newPasswordFormField', 'newPassword');
                $username = isset($body->$usernameFormFieldName) ? $body->$usernameFormFieldName : '';
                $password = isset($body->$passwordFormFieldName) ? $body->$passwordFormFieldName : '';
                $newPassword = isset($body->$newPasswordFormFieldName) ? $body->$newPasswordFormFieldName : '';
                //add separate property for login as this could be a view joining users table to other table 
                //such as roles, details etc. At a minimum, the view output should include the $usernameColumn and $passwordColumn
                if ($path === 'login') {
                    $tableName = $this->getProperty('loginTable', $this->getProperty('usersTable', 'users'));
                } else {
                    $tableName = $this->getProperty('usersTable', 'users');
                }
                $table = $this->reflection->getTable($tableName);
                $usernameColumnName = $this->getProperty('usernameColumn', 'username');
                $usernameColumn = $table->getColumn($usernameColumnName);
                $passwordColumnName = $this->getProperty('passwordColumn', 'password');
                $passwordLength = $this->getProperty('passwordLength', '12');
                $pkName = $table->getPk()->getName();
                $registerUser = $this->getProperty('registerUser', '');
                $loginAfterRegistration = $this->getProperty('loginAfterRegistration', '');
                $condition = new ColumnCondition($usernameColumn, 'eq', $username);
                $returnedColumns = $this->getProperty('returnedColumns', '');
                if (!$returnedColumns) {
                    $columnNames = $table->getColumnNames();
                } else {
                    $columnNames = array_map('trim', explode(',', $returnedColumns));
                    $columnNames[] = $passwordColumnName;
                    $columnNames = array_values(array_unique($columnNames));
                }
                $columnOrdering = $this->ordering->getDefaultColumnOrdering($table);
                if ($path == 'register') {
                    if (!$registerUser) {
                        return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
                    }
                    if (strlen(trim($username)) == 0) {
                        return $this->responder->error(ErrorCode::USERNAME_EMPTY, $username);
                    }
                    if (strlen($password) < $passwordLength) {
                        return $this->responder->error(ErrorCode::PASSWORD_TOO_SHORT, $passwordLength);
                    }
                    $users = $this->db->selectAll($table, $columnNames, $condition, $columnOrdering, 0, 1);
                    if (!empty($users)) {
                        return $this->responder->error(ErrorCode::USER_ALREADY_EXIST, $username);
                    }
                    $data = json_decode($registerUser, true);
                    $data = is_array($data) ? $data : [];
                    $data[$usernameColumnName] = $username;
                    $data[$passwordColumnName] = password_hash($password, PASSWORD_DEFAULT);
                    $this->db->createSingle($table, $data);
                    $users = $this->db->selectAll($table, $columnNames, $condition, $columnOrdering, 0, 1);
                    foreach ($users as $user) {
                        if ($loginAfterRegistration) {
                            if (!headers_sent()) {
                                session_regenerate_id(true);
                            }
                            unset($user[$passwordColumnName]);
                            $_SESSION['user'] = $user;
                            return $this->responder->success($user);
                        } else {
                            unset($user[$passwordColumnName]);
                            return $this->responder->success($user);
                        }
                    }
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
                }
                if ($path == 'login') {
                    $users = $this->db->selectAll($table, $columnNames, $condition, $columnOrdering, 0, 1);
                    foreach ($users as $user) {
                        if (password_verify($password, $user[$passwordColumnName]) == 1) {
                            if (!headers_sent()) {
                                session_regenerate_id(true);
                            }
                            unset($user[$passwordColumnName]);
                            $_SESSION['user'] = $user;
                            return $this->responder->success($user);
                        }
                    }
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
                }
                if ($path == 'password') {
                    if ($username != ($_SESSION['user'][$usernameColumnName] ?? '')) {
                        return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
                    }
                    if (strlen($newPassword) < $passwordLength) {
                        return $this->responder->error(ErrorCode::PASSWORD_TOO_SHORT, $passwordLength);
                    }
                    $userColumns = $columnNames;
                    if (!in_array($pkName, $columnNames)) {
                        array_push($userColumns, $pkName);
                    }
                    $users = $this->db->selectAll($table, $userColumns, $condition, $columnOrdering, 0, 1);
                    foreach ($users as $user) {
                        if (password_verify($password, $user[$passwordColumnName]) == 1) {
                            if (!headers_sent()) {
                                session_regenerate_id(true);
                            }
                            $data = [$passwordColumnName => password_hash($newPassword, PASSWORD_DEFAULT)];
                            $this->db->updateSingle($table, $data, $user[$pkName]);
                            unset($user[$passwordColumnName]);
                            if (!in_array($pkName, $columnNames)) {
                                unset($user[$pkName]);
                            }
                            return $this->responder->success($user);
                        }
                    }
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
                }
            }
            if ($method == 'POST' && $path == 'logout') {
                if (isset($_SESSION['user'])) {
                    $user = $_SESSION['user'];
                    unset($_SESSION['user']);
                    if (session_status() != PHP_SESSION_NONE) {
                        session_destroy();
                    }
                    return $this->responder->success($user);
                }
                return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
            }
            if ($method == 'GET' && $path == 'me') {
                if (isset($_SESSION['user'])) {
                    return $this->responder->success($_SESSION['user']);
                }
                return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
            }
            if (!isset($_SESSION['user']) || !$_SESSION['user']) {
                $authenticationMode = $this->getProperty('mode', 'required');
                if ($authenticationMode == 'required') {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    class FirewallMiddleware extends Middleware
    {
        private function ipMatch(string $ip, string $cidr): bool
        {
            if (strpos($cidr, '/') !== false) {
                list($subnet, $mask) = explode('/', trim($cidr));
                if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                    return true;
                }
            } else {
                if (ip2long($ip) == ip2long($cidr)) {
                    return true;
                }
            }
            return false;
        }
        private function isIpAllowed(string $ipAddress, string $allowedIpAddresses): bool
        {
            foreach (explode(',', $allowedIpAddresses) as $allowedIp) {
                if ($this->ipMatch($ipAddress, $allowedIp)) {
                    return true;
                }
            }
            return false;
        }
        private function getIpAddress(ServerRequestInterface $request): string
        {
            $reverseProxy = $this->getProperty('reverseProxy', '');
            if ($reverseProxy) {
                $ipAddress = array_pop($request->getHeader('X-Forwarded-For'));
            } else {
                $serverParams = $request->getServerParams();
                $ipAddress = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
            }
            return $ipAddress;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $ipAddress = $this->getIpAddress($request);
            $allowedIpAddresses = $this->getProperty('allowedIpAddresses', '');
            if (!$this->isIpAllowed($ipAddress, $allowedIpAddresses)) {
                $response = $this->responder->error(ErrorCode::TEMPORARY_OR_PERMANENTLY_BLOCKED, '');
            } else {
                $response = $next->handle($request);
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\RequestUtils;
    class IpAddressMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        private function callHandler(ServerRequestInterface $request, $record, string $operation, ReflectedTable $table) /*: object */
        {
            $context = (array) $record;
            $columnNames = $this->getProperty('columns', '');
            if ($columnNames) {
                foreach (explode(',', $columnNames) as $columnName) {
                    if ($table->hasColumn($columnName)) {
                        if ($operation == 'create') {
                            $context[$columnName] = $this->getIpAddress($request);
                        } else {
                            unset($context[$columnName]);
                        }
                    }
                }
            }
            return (object) $context;
        }
        private function getIpAddress(ServerRequestInterface $request): string
        {
            $reverseProxy = $this->getProperty('reverseProxy', '');
            if ($reverseProxy) {
                $ipAddress = array_pop($request->getHeader('X-Forwarded-For'));
            } else {
                $serverParams = $request->getServerParams();
                $ipAddress = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
            }
            return $ipAddress;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            if (in_array($operation, ['create', 'update', 'increment'])) {
                $tableNames = $this->getProperty('tables', '');
                $tableName = RequestUtils::getPathSegment($request, 2);
                if (!$tableNames || in_array($tableName, explode(',', $tableNames))) {
                    if ($this->reflection->hasTable($tableName)) {
                        $record = $request->getParsedBody();
                        if ($record !== null) {
                            $table = $this->reflection->getTable($tableName);
                            if (is_array($record)) {
                                foreach ($record as &$r) {
                                    $r = $this->callHandler($request, $r, $operation, $table);
                                }
                            } else {
                                $record = $this->callHandler($request, $record, $operation, $table);
                            }
                            $request = $request->withParsedBody($record);
                        }
                    }
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Communication\VariableStore;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\RequestUtils;
    class JoinLimitsMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            $params = RequestUtils::getParams($request);
            if (in_array($operation, ['read', 'list']) && isset($params['join'])) {
                $maxDepth = (int) $this->getProperty('depth', '3');
                $maxTables = (int) $this->getProperty('tables', '10');
                $maxRecords = (int) $this->getProperty('records', '1000');
                $tableCount = 0;
                $joinPaths = array();
                for ($i = 0; $i < count($params['join']); $i++) {
                    $joinPath = array();
                    $tables = explode(',', $params['join'][$i]);
                    for ($depth = 0; $depth < min($maxDepth, count($tables)); $depth++) {
                        array_push($joinPath, $tables[$depth]);
                        $tableCount += 1;
                        if ($tableCount == $maxTables) {
                            break;
                        }
                    }
                    array_push($joinPaths, implode(',', $joinPath));
                    if ($tableCount == $maxTables) {
                        break;
                    }
                }
                $params['join'] = $joinPaths;
                $request = RequestUtils::setParams($request, $params);
                VariableStore::set("joinLimits.maxRecords", $maxRecords);
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\RequestUtils;
    use afcodehub\gatewaypro\ResponseFactory;
    class JsonMiddleware extends Middleware
    {
        private function convertJsonRequestValue($value) /*: object */
        {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value,JSON_UNESCAPED_UNICODE);
            }
            return $value;
        }
        private function convertJsonRequest($object, array $columnNames) /*: object */
        {
            if (is_array($object)) {
                foreach ($object as $i => $obj) {
                    foreach ($obj as $k => $v) {
                        if (in_array('all', $columnNames) || in_array($k, $columnNames)) {
                            $object[$i]->$k = $this->convertJsonRequestValue($v);
                        }
                    }
                }
            } else if (is_object($object)) {
                foreach ($object as $k => $v) {
                    if (in_array('all', $columnNames) || in_array($k, $columnNames)) {
                        $object->$k = $this->convertJsonRequestValue($v);
                    }
                }
            }
            return $object;
        }
        private function convertJsonResponseValue(string $value) /*: object */
        {
            if (strlen($value) > 0 && in_array($value[0],['[','{'])) {
                $parsed = json_decode($value);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $value = $parsed;
                }
            }
            return $value;
        }
        private function convertJsonResponse($object, array $columnNames) /*: object */
        {
            if (is_array($object)) {
                foreach ($object as $k => $v) {
                    $object[$k] = $this->convertJsonResponse($v, $columnNames);
                }
            } else if (is_object($object)) {
                foreach ($object as $k => $v) {
                    if (in_array('all', $columnNames) || in_array($k, $columnNames)) {
                        $object->$k = $this->convertJsonResponse($v, $columnNames);
                    }
                }
            } else if (is_string($object)) {
                $object = $this->convertJsonResponseValue($object);
            }
            return $object;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            $controllerPath = RequestUtils::getPathSegment($request, 1);
            $tableName = RequestUtils::getPathSegment($request, 2);
            $controllerPaths = $this->getArrayProperty('controllers', 'records,geojson');
    		$tableNames = $this->getArrayProperty('tables', 'all');
    		$columnNames = $this->getArrayProperty('columns', 'all');
    		if (
    			(in_array('all', $controllerPaths) || in_array($controllerPath, $controllerPaths)) &&
    			(in_array('all', $tableNames) || in_array($tableName, $tableNames))
    		) {
                if (in_array($operation, ['create', 'update'])) {
                    $records = $request->getParsedBody();
                    $records = $this->convertJsonRequest($records,$columnNames);
                    $request = $request->withParsedBody($records);
                }
                $response = $next->handle($request);
                if (in_array($operation, ['read', 'list'])) {
                    if ($response->getStatusCode() == ResponseFactory::OK) {
                        $records = json_decode($response->getBody()->getContents());
                        $records = $this->convertJsonResponse($records, $columnNames);
                        $response = $this->responder->success($records);
                    }
                }
            } else {
                $response = $next->handle($request);
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class JwtAuthMiddleware extends Middleware
    {
        private function getVerifiedClaims(string $token, int $time, int $leeway, int $ttl, array $secrets, array $requirements): array
        {
            $algorithms = array(
                'HS256' => 'sha256',
                'HS384' => 'sha384',
                'HS512' => 'sha512',
                'RS256' => 'sha256',
                'RS384' => 'sha384',
                'RS512' => 'sha512',
            );
            $token = explode('.', $token);
            if (count($token) < 3) {
                return array();
            }
            $header = json_decode(base64_decode(strtr($token[0], '-_', '+/')), true);
            $kid = 0;
            if (isset($header['kid'])) {
                $kid = $header['kid'];
            }
            if (!isset($secrets[$kid])) {
                return array();
            }
            $secret = $secrets[$kid];
            if ($header['typ'] != 'JWT') {
                return array();
            }
            $algorithm = $header['alg'];
            if (!isset($algorithms[$algorithm])) {
                return array();
            }
            if (!empty($requirements['alg']) && !in_array($algorithm, $requirements['alg'])) {
                return array();
            }
            $hmac = $algorithms[$algorithm];
            $signature = base64_decode(strtr($token[2], '-_', '+/'));
            $data = "$token[0].$token[1]";
            switch ($algorithm[0]) {
                case 'H':
                    $hash = hash_hmac($hmac, $data, $secret, true);
                    $equals = hash_equals($hash, $signature);
                    if (!$equals) {
                        return array();
                    }
                    break;
                case 'R':
                    $equals = openssl_verify($data, $signature, $secret, $hmac) == 1;
                    if (!$equals) {
                        return array();
                    }
                    break;
            }
            $claims = json_decode(base64_decode(strtr($token[1], '-_', '+/')), true);
            if (!$claims) {
                return array();
            }
            foreach ($requirements as $field => $values) {
                if (!empty($values)) {
                    if ($field != 'alg') {
                        if (!isset($claims[$field])) {
                            return array();
                        }
                        if (is_array($claims[$field])) {
                            if (!array_intersect($claims[$field], $values)) {
                                return array();
                            }
                        } else {
                            if (!in_array($claims[$field], $values)) {
                                return array();
                            }
                        }
                    }
                }
            }
            if (isset($claims['nbf']) && $time + $leeway < $claims['nbf']) {
                return array();
            }
            if (isset($claims['iat']) && $time + $leeway < $claims['iat']) {
                return array();
            }
            if (isset($claims['exp']) && $time - $leeway > $claims['exp']) {
                return array();
            }
            if (isset($claims['iat']) && !isset($claims['exp'])) {
                if ($time - $leeway > $claims['iat'] + $ttl) {
                    return array();
                }
            }
            return $claims;
        }
        private function getClaims(string $token): array
        {
            $time = (int) $this->getProperty('time', time());
            $leeway = (int) $this->getProperty('leeway', '5');
            $ttl = (int) $this->getProperty('ttl', '30');
            $secrets = $this->getMapProperty('secrets', '');
            if (!$secrets) {
                $secrets = [$this->getProperty('secret', '')];
            }
            $requirements = array(
                'alg' => $this->getArrayProperty('algorithms', ''),
                'aud' => $this->getArrayProperty('audiences', ''),
                'iss' => $this->getArrayProperty('issuers', ''),
            );
            return $this->getVerifiedClaims($token, $time, $leeway, $ttl, $secrets, $requirements);
        }
        private function getAuthorizationToken(ServerRequestInterface $request): string
        {
            $headerName = $this->getProperty('header', 'X-Authorization');
            $headerValue = RequestUtils::getHeader($request, $headerName);
            $parts = explode(' ', trim($headerValue), 2);
            if (count($parts) != 2) {
                return '';
            }
            if ($parts[0] != 'Bearer') {
                return '';
            }
            return $parts[1];
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            if (session_status() == PHP_SESSION_NONE) {
                if (!headers_sent()) {
                    $sessionName = $this->getProperty('sessionName', '');
                    if ($sessionName) {
                        session_name($sessionName);
                    }
                    if (!ini_get('session.cookie_samesite')) {
                        ini_set('session.cookie_samesite', 'Lax');
                    }
                    if (!ini_get('session.cookie_httponly')) {
                        ini_set('session.cookie_httponly', 1);
                    }
                    if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                        ini_set('session.cookie_secure', 1);
                    }
                    session_start();
                }
            }
            $token = $this->getAuthorizationToken($request);
            if ($token) {
                $claims = $this->getClaims($token);
                $_SESSION['claims'] = $claims;
                if (empty($claims)) {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, 'JWT');
                }
                if (!headers_sent()) {
                    session_regenerate_id();
                }
            }
            if (empty($_SESSION['claims'])) {
                $authenticationMode = $this->getProperty('mode', 'required');
                if ($authenticationMode == 'required') {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Communication\VariableStore;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\Condition\ColumnCondition;
    use afcodehub\gatewaypro\Record\Condition\Condition;
    use afcodehub\gatewaypro\Record\Condition\NoCondition;
    use afcodehub\gatewaypro\RequestUtils;
    class MultiTenancyMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        private function getCondition(string $tableName, array $pairs): Condition
        {
            $condition = new NoCondition();
            $table = $this->reflection->getTable($tableName);
            foreach ($pairs as $k => $v) {
                $condition = $condition->_and(new ColumnCondition($table->getColumn($k), 'eq', $v));
            }
            return $condition;
        }
        private function getPairs($handler, string $operation, string $tableName): array
        {
            $result = array();
            $pairs = call_user_func($handler, $operation, $tableName) ?: [];
            $table = $this->reflection->getTable($tableName);
            foreach ($pairs as $k => $v) {
                if ($table->hasColumn($k)) {
                    $result[$k] = $v;
                }
            }
            return $result;
        }
        private function handleRecord(ServerRequestInterface $request, string $operation, array $pairs): ServerRequestInterface
        {
            $record = $request->getParsedBody();
            if ($record === null) {
                return $request;
            }
            $multi = is_array($record);
            $records = $multi ? $record : [$record];
            foreach ($records as &$record) {
                foreach ($pairs as $column => $value) {
                    if ($operation == 'create') {
                        $record->$column = $value;
                    } else {
                        if (isset($record->$column)) {
                            unset($record->$column);
                        }
                    }
                }
            }
            return $request->withParsedBody($multi ? $records : $records[0]);
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $handler = $this->getProperty('handler', '');
            if ($handler !== '') {
                $path = RequestUtils::getPathSegment($request, 1);
                if ($path == 'records') {
                    $operation = RequestUtils::getOperation($request);
                    $tableNames = RequestUtils::getTableNames($request, $this->reflection);
                    foreach ($tableNames as $i => $tableName) {
                        if (!$this->reflection->hasTable($tableName)) {
                            continue;
                        }
                        $pairs = $this->getPairs($handler, $operation, $tableName);
                        if ($i == 0) {
                            if (in_array($operation, ['create', 'update', 'increment'])) {
                                $request = $this->handleRecord($request, $operation, $pairs);
                            }
                        }
                        $condition = $this->getCondition($tableName, $pairs);
                        VariableStore::set("multiTenancy.conditions.$tableName", $condition);
                    }
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class PageLimitsMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            if ($operation == 'list') {
                $params = RequestUtils::getParams($request);
                $maxPage = (int) $this->getProperty('pages', '100');
                if (isset($params['page']) && $params['page'] && $maxPage > 0) {
                    if (strpos($params['page'][0], ',') === false) {
                        $page = $params['page'][0];
                    } else {
                        list($page, $size) = explode(',', $params['page'][0], 2);
                    }
                    if ($page > $maxPage) {
                        return $this->responder->error(ErrorCode::PAGINATION_FORBIDDEN, '');
                    }
                }
                $maxSize = (int) $this->getProperty('records', '1000');
                if (!isset($params['size']) || !$params['size'] && $maxSize > 0) {
                    $params['size'] = array($maxSize);
                } else {
                    $params['size'] = array(min($params['size'][0], $maxSize));
                }
                $request = RequestUtils::setParams($request, $params);
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    class ReconnectMiddleware extends Middleware
    {
        private $config;
        private $db;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection, GenericDB $db)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->config = $config;
            $this->db = $db;
        }
        private function getDriver(): string
        {
            $driverHandler = $this->getProperty('driverHandler', '');
            if ($driverHandler) {
                return call_user_func($driverHandler);
            }
            return $this->config->getDriver();
        }
        private function getAddress(): string
        {
            $addressHandler = $this->getProperty('addressHandler', '');
            if ($addressHandler) {
                return call_user_func($addressHandler);
            }
            return $this->config->getAddress();
        }
        private function getPort(): int
        {
            $portHandler = $this->getProperty('portHandler', '');
            if ($portHandler) {
                return call_user_func($portHandler);
            }
            return $this->config->getPort();
        }
        private function getDatabase(): string
        {
            $databaseHandler = $this->getProperty('databaseHandler', '');
            if ($databaseHandler) {
                return call_user_func($databaseHandler);
            }
            return $this->config->getDatabase();
        }
        private function getCommand(): string
        {
            $commandHandler = $this->getProperty('commandHandler', '');
            if ($commandHandler) {
                return call_user_func($commandHandler);
            }
            return $this->config->getCommand();
        }
        private function getTables(): array
        {
            $tablesHandler = $this->getProperty('tablesHandler', '');
            if ($tablesHandler) {
                return call_user_func($tablesHandler);
            }
            return $this->config->getTables();
        }
        private function getMapping(): array
        {
            $mappingHandler = $this->getProperty('mappingHandler', '');
            if ($mappingHandler) {
                return call_user_func($mappingHandler);
            }
            return $this->config->getMapping();
        }
        private function getUsername(): string
        {
            $usernameHandler = $this->getProperty('usernameHandler', '');
            if ($usernameHandler) {
                return call_user_func($usernameHandler);
            }
            return $this->config->getUsername();
        }
        private function getPassword(): string
        {
            $passwordHandler = $this->getProperty('passwordHandler', '');
            if ($passwordHandler) {
                return call_user_func($passwordHandler);
            }
            return $this->config->getPassword();
        }
        private function getGeometrySrid(): int
        {
            $geometrySridHandler = $this->getProperty('geometrySridHandler', '');
            if ($geometrySridHandler) {
                return call_user_func($geometrySridHandler);
            }
            return $this->config->getGeometrySrid();
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $driver = $this->getDriver();
            $address = $this->getAddress();
            $port = $this->getPort();
            $database = $this->getDatabase();
            $command = $this->getCommand();
            $tables = $this->getTables();
            $mapping = $this->getMapping();
            $username = $this->getUsername();
            $password = $this->getPassword();
            $geometrySrid = $this->getGeometrySrid();
            $this->db->reconstruct($driver, $address, $port, $database, $command, $tables, $mapping, $username, $password, $geometrySrid);
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\RequestUtils;
    class SanitationMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        private function callHandler($handler, $record, string $operation, ReflectedTable $table) /*: object */
        {
            $context = (array) $record;
            $tableName = $table->getName();
            foreach ($context as $columnName => &$value) {
                if ($table->hasColumn($columnName)) {
                    $column = $table->getColumn($columnName);
                    $value = call_user_func($handler, $operation, $tableName, $column->serialize(), $value);
                    $value = $this->sanitizeType($table, $column, $value);
                }
            }
            return (object) $context;
        }
        private function sanitizeType(ReflectedTable $table, ReflectedColumn $column, $value)
        {
            $tables = $this->getArrayProperty('tables', 'all');
            $types = $this->getArrayProperty('types', 'all');
            if (
                (in_array('all', $tables) || in_array($table->getName(), $tables)) &&
                (in_array('all', $types) || in_array($column->getType(), $types))
            ) {
                if (is_null($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    $newValue = null;
                    switch ($column->getType()) {
                        case 'integer':
                        case 'bigint':
                            $newValue = filter_var(trim($value), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                            break;
                        case 'decimal':
                            $newValue = filter_var(trim($value), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                            if (is_float($newValue)) {
                                $newValue = number_format($newValue, $column->getScale(), '.', '');
                            }
                            break;
                        case 'float':
                        case 'double':
                            $newValue = filter_var(trim($value), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                            break;
                        case 'boolean':
                            $newValue = filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                            break;
                        case 'date':
                            $time = strtotime(trim($value));
                            if ($time !== false) {
                                $newValue = date('Y-m-d', $time);
                            }
                            break;
                        case 'time':
                            $time = strtotime(trim($value));
                            if ($time !== false) {
                                $newValue = date('H:i:s', $time);
                            }
                            break;
                        case 'timestamp':
                            $time = strtotime(trim($value));
                            if ($time !== false) {
                                $newValue = date('Y-m-d H:i:s', $time);
                            }
                            break;
                        case 'blob':
                        case 'varbinary':
                            // allow base64url format
                            $newValue = strtr(trim($value), '-_', '+/');
                            break;
                        case 'clob':
                        case 'varchar':
                            $newValue = $value;
                            break;
                        case 'geometry':
                            $newValue = trim($value);
                            break;
                    }
                    if (!is_null($newValue)) {
                        $value = $newValue;
                    }
                } else {
                    switch ($column->getType()) {
                        case 'integer':
                        case 'bigint':
                            if (is_float($value)) {
                                $value = (int) round($value);
                            }
                            break;
                        case 'decimal':
                            if (is_float($value) || is_int($value)) {
                                $value = number_format((float) $value, $column->getScale(), '.', '');
                            }
                            break;
                    }
                }
                // post process
            }
            return $value;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            if (in_array($operation, ['create', 'update', 'increment'])) {
                $tableName = RequestUtils::getPathSegment($request, 2);
                if ($this->reflection->hasTable($tableName)) {
                    $record = $request->getParsedBody();
                    if ($record !== null) {
                        $handler = $this->getProperty('handler', '');
                        if ($handler !== '') {
                            $table = $this->reflection->getTable($tableName);
                            if (is_array($record)) {
                                foreach ($record as &$r) {
                                    $r = $this->callHandler($handler, $r, $operation, $table);
                                }
                            } else {
                                $record = $this->callHandler($handler, $record, $operation, $table);
                            }
                            $request = $request->withParsedBody($record);
                        }
                    }
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\ResponseFactory;
    class SslRedirectMiddleware extends Middleware
    {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $uri = $request->getUri();
            $scheme = $uri->getScheme();
            if ($scheme == 'http') {
                $uri = $request->getUri();
                $uri = $uri->withScheme('https');
                $response = ResponseFactory::fromStatus(301);
                $response = $response->withHeader('Location', $uri->__toString());
            } else {
                $response = $next->handle($request);
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\RequestUtils;
    class TextSearchMiddleware extends Middleware
    {
        private $reflection;
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
        {
            parent::__construct($router, $responder, $config, $middleware);
            $this->reflection = $reflection;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $operation = RequestUtils::getOperation($request);
            if ($operation == 'list') {
                $tableName = RequestUtils::getPathSegment($request, 2);
                $params = RequestUtils::getParams($request);
                $parameterName = $this->getProperty('parameter', 'search');
                if (isset($params[$parameterName])) {
                    $search = $params[$parameterName][0];
                    unset($params[$parameterName]);
                    $table = $this->reflection->getTable($tableName);
                    $i = 0;
                    foreach ($table->getColumnNames() as $columnName) {
                        $column = $table->getColumn($columnName);
                        while (isset($params["filter$i"])) {
                            $i++;
                        }
                        if ($i >= 10) {
                            break;
                        }
                        if ($column->isText()) {
                            $params["filter$i"] = "$columnName,cs,$search";
                            $i++;
                        }
                    }
                }
                $request = RequestUtils::setParams($request, $params);
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class ValidationMiddleware extends Middleware
    {
    	private $reflection;
    	public function __construct(Router $router, Responder $responder, Config $config, string $middleware, ReflectionService $reflection)
    	{
    		parent::__construct($router, $responder, $config, $middleware);
    		$this->reflection = $reflection;
    	}
    	private function callHandler($handler, $record, string $operation, ReflectedTable $table) /*: ResponseInterface?*/
    	{
    		$context = (array) $record;
    		$details = array();
    		$tableName = $table->getName();
    		foreach ($context as $columnName => $value) {
    			if ($table->hasColumn($columnName)) {
    				$column = $table->getColumn($columnName);
    				$valid = call_user_func($handler, $operation, $tableName, $column->serialize(), $value, $context);
    				if ($valid === true || $valid === '') {
    					$valid = $this->validateType($table, $column, $value);
    				}
    				if ($valid !== true && $valid !== '') {
    					$details[$columnName] = $valid;
    				}
    			}
    		}
    		if (count($details) > 0) {
    			return $this->responder->error(ErrorCode::INPUT_VALIDATION_FAILED, $tableName, $details);
    		}
    		return null;
    	}
    	private function validateType(ReflectedTable $table, ReflectedColumn $column, $value)
    	{
    		$tables = $this->getArrayProperty('tables', 'all');
    		$types = $this->getArrayProperty('types', 'all');
    		if (
    			(in_array('all', $tables) || in_array($table->getName(), $tables)) &&
    			(in_array('all', $types) || in_array($column->getType(), $types))
    		) {
    			if (is_null($value)) {
    				return ($column->getNullable() ? true : "cannot be null");
    			}
    			if (is_string($value)) {
    				// check for whitespace
    				switch ($column->getType()) {
    					case 'varchar':
    					case 'clob':
    						break;
    					default:
    						if (strlen(trim($value)) != strlen($value)) {
    							return 'illegal whitespace';
    						}
    						break;
    				}
    				// try to parse
    				switch ($column->getType()) {
    					case 'integer':
    					case 'bigint':
    						if (
    							filter_var($value, FILTER_SANITIZE_NUMBER_INT) !== $value ||
    							filter_var($value, FILTER_VALIDATE_INT) === false
    						) {
    							return 'invalid integer';
    						}
    						break;
    					case 'decimal':
    						if (strpos($value, '.') !== false) {
    							list($whole, $decimals) = explode('.', ltrim($value, '-'), 2);
    						} else {
    							list($whole, $decimals) = array(ltrim($value, '-'), '');
    						}
    						if (strlen($whole) > 0 && !ctype_digit($whole)) {
    							return 'invalid decimal';
    						}
    						if (strlen($decimals) > 0 && !ctype_digit($decimals)) {
    							return 'invalid decimal';
    						}
    						if (strlen($whole) > $column->getPrecision() - $column->getScale()) {
    							return 'decimal too large';
    						}
    						if (strlen($decimals) > $column->getScale()) {
    							return 'decimal too precise';
    						}
    						break;
    					case 'float':
    					case 'double':
    						if (
    							filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT) !== $value ||
    							filter_var($value, FILTER_VALIDATE_FLOAT) === false
    						) {
    							return 'invalid float';
    						}
    						break;
    					case 'boolean':
    						if (!in_array(strtolower($value), array('true', 'false'))) {
    							return 'invalid boolean';
    						}
    						break;
    					case 'date':
    						if (date_create_from_format('Y-m-d', $value) === false) {
    							return 'invalid date';
    						}
    						break;
    					case 'time':
    						if (date_create_from_format('H:i:s', $value) === false) {
    							return 'invalid time';
    						}
    						break;
    					case 'timestamp':
    						if (date_create_from_format('Y-m-d H:i:s', $value) === false) {
    							return 'invalid timestamp';
    						}
    						break;
    					case 'clob':
    					case 'varchar':
    						if ($column->hasLength() && mb_strlen($value, 'UTF-8') > $column->getLength()) {
    							return 'string too long';
    						}
    						break;
    					case 'blob':
    					case 'varbinary':
    						if (base64_decode($value, true) === false) {
    							return 'invalid base64';
    						}
    						if ($column->hasLength() && strlen(base64_decode($value)) > $column->getLength()) {
    							return 'string too long';
    						}
    						break;
    					case 'geometry':
    						// no checks yet
    						break;
    				}
    			} else { // check non-string types
    				switch ($column->getType()) {
    					case 'integer':
    					case 'bigint':
    						if (!is_int($value)) {
    							return 'invalid integer';
    						}
    						break;
    					case 'float':
    					case 'double':
    						if (!is_float($value) && !is_int($value)) {
    							return 'invalid float';
    						}
    						break;
    					case 'boolean':
    						if (!is_bool($value) && ($value !== 0) && ($value !== 1)) {
    							return 'invalid boolean';
    						}
    						break;
    					default:
    						return 'invalid ' . $column->getType();
    				}
    			}
    			// extra checks
    			switch ($column->getType()) {
    				case 'integer': // 4 byte signed
    					$value = filter_var($value, FILTER_VALIDATE_INT);
    					if ($value > 2147483647 || $value < -2147483648) {
    						return 'invalid integer';
    					}
    					break;
    			}
    		}
    		return (true);
    	}
    	public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    	{
    		$operation = RequestUtils::getOperation($request);
    		if (in_array($operation, ['create', 'update', 'increment'])) {
    			$tableName = RequestUtils::getPathSegment($request, 2);
    			if ($this->reflection->hasTable($tableName)) {
    				$record = $request->getParsedBody();
    				if ($record !== null) {
    					$handler = $this->getProperty('handler', '');
    					if ($handler !== '') {
    						$table = $this->reflection->getTable($tableName);
    						if (is_array($record)) {
    							foreach ($record as $r) {
    								$response = $this->callHandler($handler, $r, $operation, $table);
    								if ($response !== null) {
    									return $response;
    								}
    							}
    						} else {
    							$response = $this->callHandler($handler, $record, $operation, $table);
    							if ($response !== null) {
    								return $response;
    							}
    						}
    					}
    				}
    			}
    		}
    		return $next->handle($request);
    	}
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\Record\ErrorCode;
    use afcodehub\gatewaypro\RequestUtils;
    class WpAuthMiddleware extends Middleware
    {
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware)
        {
            parent::__construct($router, $responder, $config, $middleware);
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            define('WP_USE_THEMES', false); // Don't load theme support functionality
            $wpDirectory = $this->getProperty('wpDirectory', '.');
            require_once("$wpDirectory/wp-load.php");
            $path = RequestUtils::getPathSegment($request, 1);
            $method = $request->getMethod();
            if ($method == 'POST' && $path == 'login') {
                $body = $request->getParsedBody();
                $usernameFormFieldName = $this->getProperty('usernameFormField', 'username');
                $passwordFormFieldName = $this->getProperty('passwordFormField', 'password');
                $username = isset($body->$usernameFormFieldName) ? $body->$usernameFormFieldName : '';
                $password = isset($body->$passwordFormFieldName) ? $body->$passwordFormFieldName : '';
                $user = wp_signon([
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => false,
                ]);
                if ($user->ID) {
                    unset($user->data->user_pass);
                    return $this->responder->success($user);
                }
                return $this->responder->error(ErrorCode::AUTHENTICATION_FAILED, $username);
            }
            if ($method == 'POST' && $path == 'logout') {
                if (is_user_logged_in()) {
                    wp_logout();
                    $user = wp_get_current_user();
                    unset($user->data->user_pass);
                    return $this->responder->success($user);
                }
                return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
            }
            if ($method == 'GET' && $path == 'me') {
                if (is_user_logged_in()) {
                    $user = wp_get_current_user();
                    unset($user->data->user_pass);
                    return $this->responder->success($user);
                }
                return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
            }
            if (!is_user_logged_in()) {
                $authenticationMode = $this->getProperty('mode', 'required');
                if ($authenticationMode == 'required') {
                    return $this->responder->error(ErrorCode::AUTHENTICATION_REQUIRED, '');
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Middleware\Router\Router;
    use afcodehub\gatewaypro\ResponseFactory;
    class XmlMiddleware extends Middleware
    {
        public function __construct(Router $router, Responder $responder, Config $config, string $middleware)
        {
            parent::__construct($router, $responder, $config, $middleware);
        }
        private function json2xml($json, $types = 'null,boolean,number,string,object,array')
        {
            $a = json_decode($json);
            $d = new \DOMDocument();
            $c = $d->createElement("root");
            $d->appendChild($c);
            $t = function ($v) {
                $type = gettype($v);
                switch ($type) {
                    case 'integer':
                        return 'number';
                    case 'double':
                        return 'number';
                    default:
                        return strtolower($type);
                }
            };
            $ts = explode(',', $types);
            $f = function ($f, $c, $a, $s = false) use ($t, $d, $ts) {
                if (in_array($t($a), $ts)) {
                    $c->setAttribute('type', $t($a));
                }
                if ($t($a) != 'array' && $t($a) != 'object') {
                    if ($t($a) == 'boolean') {
                        $c->appendChild($d->createTextNode($a ? 'true' : 'false'));
                    } else {
                        $c->appendChild($d->createTextNode($a));
                    }
                } else {
                    foreach ($a as $k => $v) {
                        $k = preg_replace('/[^a-z0-9\-\_\.]/', '', $k);
                        if ($k == '__type' && $t($a) == 'object') {
                            $c->setAttribute('__type', $v);
                        } else {
                            if ($t($v) == 'object') {
                                $ch = $c->appendChild($d->createElementNS(null, $s ? 'item' : $k));
                                $f($f, $ch, $v);
                            } else if ($t($v) == 'array') {
                                $ch = $c->appendChild($d->createElementNS(null, $s ? 'item' : $k));
                                $f($f, $ch, $v, true);
                            } else {
                                $va = $d->createElementNS(null, $s ? 'item' : $k);
                                if ($t($v) == 'boolean') {
                                    $va->appendChild($d->createTextNode($v ? 'true' : 'false'));
                                } else {
                                    $va->appendChild($d->createTextNode((string) $v));
                                }
                                $ch = $c->appendChild($va);
                                if (in_array($t($v), $ts)) {
                                    $ch->setAttribute('type', $t($v));
                                }
                            }
                        }
                    }
                }
            };
            $f($f, $c, $a, $t($a) == 'array');
            return $d->saveXML($d->documentElement);
        }
        private function xml2json($xml): string
        {
            $o = @simplexml_load_string($xml);
            if ($o === false) {
                return '';
            }
            $a = @dom_import_simplexml($o);
            if (!$a) {
                return '';
            }
            $t = function ($v) {
                $t = $v->getAttribute('type');
                $txt = $v->firstChild->nodeType == XML_TEXT_NODE;
                return $t ?: ($txt ? 'string' : 'object');
            };
            $f = function ($f, $a) use ($t) {
                $c = null;
                if ($t($a) == 'null') {
                    $c = null;
                } else if ($t($a) == 'boolean') {
                    $b = substr(strtolower($a->textContent), 0, 1);
                    $c = in_array($b, array('1', 't'));
                } else if ($t($a) == 'number') {
                    $c = $a->textContent + 0;
                } else if ($t($a) == 'string') {
                    $c = $a->textContent;
                } else if ($t($a) == 'object') {
                    $c = array();
                    if ($a->getAttribute('__type')) {
                        $c['__type'] = $a->getAttribute('__type');
                    }
                    for ($i = 0; $i < $a->childNodes->length; $i++) {
                        $v = $a->childNodes[$i];
                        $c[$v->nodeName] = $f($f, $v);
                    }
                    $c = (object) $c;
                } else if ($t($a) == 'array') {
                    $c = array();
                    for ($i = 0; $i < $a->childNodes->length; $i++) {
                        $v = $a->childNodes[$i];
                        $c[$i] = $f($f, $v);
                    }
                }
                return $c;
            };
            $c = $f($f, $a);
            return (string) json_encode($c);
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            parse_str($request->getUri()->getQuery(), $params);
            $isXml = isset($params['format']) && $params['format'] == 'xml';
            if ($isXml) {
                $body = $request->getBody()->getContents();
                if ($body) {
                    $json = $this->xml2json($body);
                    $request = $request->withParsedBody(json_decode($json));
                }
            }
            $response = $next->handle($request);
            if ($isXml) {
                $body = $response->getBody()->getContents();
                if ($body) {
                    $types = implode(',', $this->getArrayProperty('types', 'null,array'));
                    if ($types == '' || $types == 'all') {
                        $xml = $this->json2xml($body);
                    } else {
                        $xml = $this->json2xml($body, $types);
                    }
                    $response = ResponseFactory::fromXml(ResponseFactory::OK, $xml);
                }
            }
            return $response;
        }
    }
}
namespace afcodehub\gatewaypro\Middleware {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Controller\Responder;
    use afcodehub\gatewaypro\Middleware\Base\Middleware;
    use afcodehub\gatewaypro\Record\ErrorCode;
    class XsrfMiddleware extends Middleware
    {
        private function getToken(ServerRequestInterface $request): string
        {
            $cookieName = $this->getProperty('cookieName', 'XSRF-TOKEN');
            $cookieParams = $request->getCookieParams();
            if (isset($cookieParams[$cookieName])) {
                $token = $cookieParams[$cookieName];
            } else {
                $secure = $request->getUri()->getScheme() == 'https';
                $token = bin2hex(random_bytes(8));
                if (!headers_sent()) {
                    setcookie($cookieName, $token, 0, '/', '', $secure);
                }
            }
            return $token;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
        {
            $token = $this->getToken($request);
            $method = $request->getMethod();
            $excludeMethods = $this->getArrayProperty('excludeMethods', 'OPTIONS,GET');
            if (!in_array($method, $excludeMethods)) {
                $headerName = $this->getProperty('headerName', 'X-XSRF-TOKEN');
                if ($token != $request->getHeader($headerName)[0]) {
                    return $this->responder->error(ErrorCode::BAD_OR_MISSING_XSRF_TOKEN, '');
                }
            }
            return $next->handle($request);
        }
    }
}
namespace afcodehub\gatewaypro\OpenApi {
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\OpenApi\OpenApiDefinition;
    class OpenApiBuilder
    {
        private $openapi;
        private $records;
        private $columns;
        private $status;
        private $builders;
        public function __construct(ReflectionService $reflection, array $base, array $controllers, array $builders)
        {
            $this->openapi = new OpenApiDefinition($base);
            $this->records = in_array('records', $controllers) ? new OpenApiRecordsBuilder($this->openapi, $reflection) : null;
            $this->columns = in_array('columns', $controllers) ? new OpenApiColumnsBuilder($this->openapi) : null;
            $this->status = in_array('status', $controllers) ? new OpenApiStatusBuilder($this->openapi) : null;
            $this->builders = array();
            foreach ($builders as $className) {
                $this->builders[] = new $className($this->openapi, $reflection);
            }
        }
        private function getServerUrl(ServerRequestInterface $request): string
        {
            $uri = $request->getUri();
            $path = $uri->getPath();
            $uri = $uri->withPath(trim(substr($path, 0, strpos($path, '/openapi')), '/'));
            return $uri->__toString();
        }
        public function build(ServerRequestInterface $request): OpenApiDefinition
        {
            $this->openapi->set("openapi", "3.0.0");
            if (!$this->openapi->has("servers")) {
                $this->openapi->set("servers||url", $this->getServerUrl($request));
            }
            if ($this->records) {
                $this->records->build();
            }
            if ($this->columns) {
                $this->columns->build();
            }
            if ($this->status) {
                $this->status->build();
            }
            foreach ($this->builders as $builder) {
                $builder->build();
            }
            return $this->openapi;
        }
    }
}
namespace afcodehub\gatewaypro\OpenApi {
    use afcodehub\gatewaypro\OpenApi\OpenApiDefinition;
    class OpenApiColumnsBuilder
    {
        private $openapi;
        private $operations = [
            'database' => [
                'read' => 'get',
            ],
            'table' => [
                'create' => 'post',
                'read' => 'get',
                'update' => 'put', //rename
                'delete' => 'delete',
            ],
            'column' => [
                'create' => 'post',
                'read' => 'get',
                'update' => 'put',
                'delete' => 'delete',
            ],
        ];
        public function __construct(OpenApiDefinition $openapi)
        {
            $this->openapi = $openapi;
        }
        public function build() /*: void*/
        {
            $this->setPaths();
            $this->openapi->set("components|responses|bool-success|description", "boolean indicating success or failure");
            $this->openapi->set("components|responses|bool-success|content|application/json|schema|type", "boolean");
            $this->setComponentSchema();
            $this->setComponentResponse();
            $this->setComponentRequestBody();
            $this->setComponentParameters();
            foreach (array_keys($this->operations) as $type) {
                $this->setTag($type);
            }
        }
        private function setPaths() /*: void*/
        {
            foreach (array_keys($this->operations) as $type) {
                foreach ($this->operations[$type] as $operation => $method) {
                    $parameters = [];
                    switch ($type) {
                        case 'database':
                            $path = '/columns';
                            break;
                        case 'table':
                            $path = $operation == 'create' ? '/columns' : '/columns/{table}';
                            break;
                        case 'column':
                            $path = $operation == 'create' ? '/columns/{table}' : '/columns/{table}/{column}';
                            break;
                    }
                    if (strpos($path, '{table}')) {
                        $parameters[] = 'table';
                    }
                    if (strpos($path, '{column}')) {
                        $parameters[] = 'column';
                    }
                    foreach ($parameters as $parameter) {
                        $this->openapi->set("paths|$path|$method|parameters||\$ref", "#/components/parameters/$parameter");
                    }
                    if (in_array($operation, ['create', 'update'])) {
                        $this->openapi->set("paths|$path|$method|requestBody|\$ref", "#/components/requestBodies/$operation-$type");
                    }
                    $this->openapi->set("paths|$path|$method|tags|", "$type");
                    $this->openapi->set("paths|$path|$method|operationId", "$operation" . "_" . "$type");
                    if ("$operation-$type" == 'update-table') {
                        $this->openapi->set("paths|$path|$method|description", "rename table");
                    } else {
                        $this->openapi->set("paths|$path|$method|description", "$operation $type");
                    }
                    switch ($operation) {
                        case 'read':
                            $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/$operation-$type");
                            break;
                        case 'create':
                        case 'update':
                        case 'delete':
                            $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/bool-success");
                            break;
                    }
                }
            }
        }
        private function setComponentSchema() /*: void*/
        {
            foreach (array_keys($this->operations) as $type) {
                foreach (array_keys($this->operations[$type]) as $operation) {
                    if ($operation == 'delete') {
                        continue;
                    }
                    $prefix = "components|schemas|$operation-$type";
                    $this->openapi->set("$prefix|type", "object");
                    switch ($type) {
                        case 'database':
                            $this->openapi->set("$prefix|properties|tables|type", 'array');
                            $this->openapi->set("$prefix|properties|tables|items|\$ref", "#/components/schemas/read-table");
                            break;
                        case 'table':
                            if ($operation == 'update') {
                                $this->openapi->set("$prefix|required", ['name']);
                                $this->openapi->set("$prefix|properties|name|type", 'string');
                            } else {
                                $this->openapi->set("$prefix|properties|name|type", 'string');
                                if ($operation == 'read') {
                                    $this->openapi->set("$prefix|properties|type|type", 'string');
                                }
                                $this->openapi->set("$prefix|properties|columns|type", 'array');
                                $this->openapi->set("$prefix|properties|columns|items|\$ref", "#/components/schemas/read-column");
                            }
                            break;
                        case 'column':
                            $this->openapi->set("$prefix|required", ['name', 'type']);
                            $this->openapi->set("$prefix|properties|name|type", 'string');
                            $this->openapi->set("$prefix|properties|type|type", 'string');
                            $this->openapi->set("$prefix|properties|length|type", 'integer');
                            $this->openapi->set("$prefix|properties|length|format", "int64");
                            $this->openapi->set("$prefix|properties|precision|type", 'integer');
                            $this->openapi->set("$prefix|properties|precision|format", "int64");
                            $this->openapi->set("$prefix|properties|scale|type", 'integer');
                            $this->openapi->set("$prefix|properties|scale|format", "int64");
                            $this->openapi->set("$prefix|properties|nullable|type", 'boolean');
                            $this->openapi->set("$prefix|properties|pk|type", 'boolean');
                            $this->openapi->set("$prefix|properties|fk|type", 'string');
                            break;
                    }
                }
            }
        }
        private function setComponentResponse() /*: void*/
        {
            foreach (array_keys($this->operations) as $type) {
                foreach (array_keys($this->operations[$type]) as $operation) {
                    if ($operation != 'read') {
                        continue;
                    }
                    $this->openapi->set("components|responses|$operation-$type|description", "single $type record");
                    $this->openapi->set("components|responses|$operation-$type|content|application/json|schema|\$ref", "#/components/schemas/$operation-$type");
                }
            }
        }
        private function setComponentRequestBody() /*: void*/
        {
            foreach (array_keys($this->operations) as $type) {
                foreach (array_keys($this->operations[$type]) as $operation) {
                    if (!in_array($operation, ['create', 'update'])) {
                        continue;
                    }
                    $this->openapi->set("components|requestBodies|$operation-$type|description", "single $type record");
                    $this->openapi->set("components|requestBodies|$operation-$type|content|application/json|schema|\$ref", "#/components/schemas/$operation-$type");
                }
            }
        }
        private function setComponentParameters() /*: void*/
        {
            $this->openapi->set("components|parameters|table|name", "table");
            $this->openapi->set("components|parameters|table|in", "path");
            $this->openapi->set("components|parameters|table|schema|type", "string");
            $this->openapi->set("components|parameters|table|description", "table name");
            $this->openapi->set("components|parameters|table|required", true);
            $this->openapi->set("components|parameters|column|name", "column");
            $this->openapi->set("components|parameters|column|in", "path");
            $this->openapi->set("components|parameters|column|schema|type", "string");
            $this->openapi->set("components|parameters|column|description", "column name");
            $this->openapi->set("components|parameters|column|required", true);
        }
        private function setTag(string $type) /*: void*/
        {
            $this->openapi->set("tags|", ['name' => $type, 'description' => "$type operations"]);
        }
    }
}
namespace afcodehub\gatewaypro\OpenApi {
    class OpenApiDefinition implements \JsonSerializable
    {
        private $root;
        public function __construct(array $base)
        {
            $this->root = $base;
        }
        public function set(string $path, $value) /*: void*/
        {
            $parts = explode('|', $path);
            $current = &$this->root;
            while (count($parts) > 0) {
                $part = array_shift($parts);
                if ($part === '') {
                    $part = count($current);
                } 
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
            $current = $value;
        }
        public function has(string $path): bool
        {
            $parts = explode('|', trim($path, '|'));
            $current = &$this->root;
            while (count($parts) > 0) {
                $part = array_shift($parts);
                if (!isset($current[$part])) {
                    return false;
                }
                $current = &$current[$part];
            }
            return true;
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->root;
        }
    }
}
namespace afcodehub\gatewaypro\OpenApi {
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    use afcodehub\gatewaypro\Middleware\Communication\VariableStore;
    use afcodehub\gatewaypro\OpenApi\OpenApiDefinition;
    class OpenApiRecordsBuilder
    {
        private $openapi;
        private $reflection;
        private $operations = [
            'list' => 'get',
            'create' => 'post',
            'read' => 'get',
            'update' => 'put',
            'delete' => 'delete',
            'increment' => 'patch',
        ];
        private $types = [
            'integer' => ['type' => 'integer', 'format' => 'int32'],
            'bigint' => ['type' => 'integer', 'format' => 'int64'],
            'varchar' => ['type' => 'string'],
            'clob' => ['type' => 'string', 'format' => 'large-string'], //custom format
            'varbinary' => ['type' => 'string', 'format' => 'byte'],
            'blob' => ['type' => 'string', 'format' => 'large-byte'], //custom format
            'decimal' => ['type' => 'string', 'format' => 'decimal'], //custom format
            'float' => ['type' => 'number', 'format' => 'float'],
            'double' => ['type' => 'number', 'format' => 'double'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'time' => ['type' => 'string', 'format' => 'time'], //custom format
            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
            'geometry' => ['type' => 'string', 'format' => 'geometry'], //custom format
            'boolean' => ['type' => 'boolean'],
        ];
        private function normalize(string $value): string
        {
            return iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        }
        public function __construct(OpenApiDefinition $openapi, ReflectionService $reflection)
        {
            $this->openapi = $openapi;
            $this->reflection = $reflection;
        }
        private function getAllTableReferences(): array
        {
            $tableReferences = array();
            foreach ($this->reflection->getTableNames() as $tableName) {
                $table = $this->reflection->getTable($tableName);
                foreach ($table->getColumnNames() as $columnName) {
                    $column = $table->getColumn($columnName);
                    $referencedTableName = $column->getFk();
                    if ($referencedTableName) {
                        if (!isset($tableReferences[$referencedTableName])) {
                            $tableReferences[$referencedTableName] = array();
                        }
                        $tableReferences[$referencedTableName][] = "$tableName.$columnName";
                    }
                }
            }
            return $tableReferences;
        }
        public function build() /*: void*/
        {
            $tableNames = $this->reflection->getTableNames();
            foreach ($tableNames as $tableName) {
                $this->setPath($tableName);
            }
            $this->openapi->set("components|responses|pk_integer|description", "inserted primary key value (integer)");
            $this->openapi->set("components|responses|pk_integer|content|application/json|schema|type", "integer");
            $this->openapi->set("components|responses|pk_integer|content|application/json|schema|format", "int64");
            $this->openapi->set("components|responses|pk_string|description", "inserted primary key value (string)");
            $this->openapi->set("components|responses|pk_string|content|application/json|schema|type", "string");
            $this->openapi->set("components|responses|pk_string|content|application/json|schema|format", "uuid");
            $this->openapi->set("components|responses|rows_affected|description", "number of rows affected (integer)");
            $this->openapi->set("components|responses|rows_affected|content|application/json|schema|type", "integer");
            $this->openapi->set("components|responses|rows_affected|content|application/json|schema|format", "int64");
            $tableReferences = $this->getAllTableReferences();
            foreach ($tableNames as $tableName) {
                $references = isset($tableReferences[$tableName]) ? $tableReferences[$tableName] : array();
                $this->setComponentSchema($tableName, $references);
                $this->setComponentResponse($tableName);
                $this->setComponentRequestBody($tableName);
            }
            $this->setComponentParameters();
            foreach ($tableNames as $tableName) {
                $this->setTag($tableName);
            }
        }
        private function isOperationOnTableAllowed(string $operation, string $tableName): bool
        {
            $tableHandler = VariableStore::get('authorization.tableHandler');
            if (!$tableHandler) {
                return true;
            }
            return (bool) call_user_func($tableHandler, $operation, $tableName);
        }
        private function isOperationOnColumnAllowed(string $operation, string $tableName, string $columnName): bool
        {
            $columnHandler = VariableStore::get('authorization.columnHandler');
            if (!$columnHandler) {
                return true;
            }
            return (bool) call_user_func($columnHandler, $operation, $tableName, $columnName);
        }
        private function setPath(string $tableName) /*: void*/
        {
            $normalizedTableName = $this->normalize($tableName);
            $table = $this->reflection->getTable($tableName);
            $type = $table->getType();
            $pk = $table->getPk();
            $pkName = $pk ? $pk->getName() : '';
            foreach ($this->operations as $operation => $method) {
                if (!$pkName && $operation != 'list') {
                    continue;
                }
                if ($type != 'table' && $operation != 'list') {
                    continue;
                }
                if (!$this->isOperationOnTableAllowed($operation, $tableName)) {
                    continue;
                }
                $parameters = [];
                if (in_array($operation, ['list', 'create'])) {
                    $path = sprintf('/records/%s', $tableName);
                    if ($operation == 'list') {
                        $parameters = ['filter', 'include', 'exclude', 'order', 'size', 'page', 'join'];
                    }
                } else {
                    $path = sprintf('/records/%s/{id}', $tableName);
                    if ($operation == 'read') {
                        $parameters = ['pk', 'include', 'exclude', 'join'];
                    } else {
                        $parameters = ['pk'];
                    }
                }
                foreach ($parameters as $parameter) {
                    $this->openapi->set("paths|$path|$method|parameters||\$ref", "#/components/parameters/$parameter");
                }
                if (in_array($operation, ['create', 'update', 'increment'])) {
                    $this->openapi->set("paths|$path|$method|requestBody|\$ref", "#/components/requestBodies/$operation-$normalizedTableName");
                }
                $this->openapi->set("paths|$path|$method|tags|", "$tableName");
                $this->openapi->set("paths|$path|$method|operationId", "$operation" . "_" . "$normalizedTableName");
                $this->openapi->set("paths|$path|$method|description", "$operation $tableName");
                switch ($operation) {
                    case 'list':
                        $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/$operation-$normalizedTableName");
                        break;
                    case 'create':
                        if ($pk->getType() == 'integer') {
                            $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/pk_integer");
                        } else {
                            $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/pk_string");
                        }
                        break;
                    case 'read':
                        $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/$operation-$normalizedTableName");
                        break;
                    case 'update':
                    case 'delete':
                    case 'increment':
                        $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/rows_affected");
                        break;
                }
            }
        }
        private function getPattern(ReflectedColumn $column): string
        {
            switch ($column->getType()) {
                case 'integer':
                    $n = strlen(pow(2, 31));
                    return '^-?[0-9]{1,' . $n . '}$';
                case 'bigint':
                    $n = strlen(pow(2, 63));
                    return '^-?[0-9]{1,' . $n . '}$';
                case 'varchar':
                    $l = $column->getLength();
                    return '^.{0,' . $l . '}$';
                case 'clob':
                    return '^.*$';
                case 'varbinary':
                    $l = $column->getLength();
                    $b = (int) 4 * ceil($l / 3);
                    return '^[A-Za-z0-9+/]{0,' . $b . '}=*$';
                case 'blob':
                    return '^[A-Za-z0-9+/]*=*$';
                case 'decimal':
                    $p = $column->getPrecision();
                    $s = $column->getScale();
                    return '^-?[0-9]{1,' . ($p - $s) . '}(\.[0-9]{1,' . $s . '})?$';
                case 'float':
                    return '^-?[0-9]+(\.[0-9]+)?([eE]-?[0-9]+)?$';
                case 'double':
                    return '^-?[0-9]+(\.[0-9]+)?([eE]-?[0-9]+)?$';
                case 'date':
                    return '^[0-9]{4}-[0-9]{2}-[0-9]{2}$';
                case 'time':
                    return '^[0-9]{2}:[0-9]{2}:[0-9]{2}$';
                case 'timestamp':
                    return '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$';
                    return '';
                case 'geometry':
                    return '^(POINT|LINESTRING|POLYGON|MULTIPOINT|MULTILINESTRING|MULTIPOLYGON)\s*\(.*$';
                case 'boolean':
                    return '^(true|false)$';
            }
            return '';
        }
        private function setComponentSchema(string $tableName, array $references) /*: void*/
        {
            $normalizedTableName = $this->normalize($tableName);
            $table = $this->reflection->getTable($tableName);
            $type = $table->getType();
            $pk = $table->getPk();
            $pkName = $pk ? $pk->getName() : '';
            foreach ($this->operations as $operation => $method) {
                if (!$pkName && $operation != 'list') {
                    continue;
                }
                if ($type == 'view' && !in_array($operation, array('read', 'list'))) {
                    continue;
                }
                if ($type == 'view' && !$pkName && $operation == 'read') {
                    continue;
                }
                if ($operation == 'delete') {
                    continue;
                }
                if (!$this->isOperationOnTableAllowed($operation, $tableName)) {
                    continue;
                }
                if ($operation == 'list') {
                    $this->openapi->set("components|schemas|$operation-$normalizedTableName|type", "object");
                    $this->openapi->set("components|schemas|$operation-$normalizedTableName|properties|results|type", "integer");
                    $this->openapi->set("components|schemas|$operation-$normalizedTableName|properties|results|format", "int64");
                    $this->openapi->set("components|schemas|$operation-$normalizedTableName|properties|records|type", "array");
                    $prefix = "components|schemas|$operation-$normalizedTableName|properties|records|items";
                } else {
                    $prefix = "components|schemas|$operation-$normalizedTableName";
                }
                $this->openapi->set("$prefix|type", "object");
                foreach ($table->getColumnNames() as $columnName) {
                    if (!$this->isOperationOnColumnAllowed($operation, $tableName, $columnName)) {
                        continue;
                    }
                    $column = $table->getColumn($columnName);
                    $properties = $this->types[$column->getType()];
                    $properties['maxLength'] = $column->hasLength() ? $column->getLength() : 0;
                    $properties['nullable'] = $column->getNullable();
                    $properties['pattern'] = $this->getPattern($column);
                    foreach ($properties as $key => $value) {
                        if ($value) {
                            $this->openapi->set("$prefix|properties|$columnName|$key", $value);
                        }
                    }
                    if ($column->getPk()) {
                        $this->openapi->set("$prefix|properties|$columnName|x-primary-key", true);
                        $this->openapi->set("$prefix|properties|$columnName|x-referenced", $references);
                    }
                    $fk = $column->getFk();
                    if ($fk) {
                        $this->openapi->set("$prefix|properties|$columnName|x-references", $fk);
                    }
                }
            }
        }
        private function setComponentResponse(string $tableName) /*: void*/
        {
            $normalizedTableName = $this->normalize($tableName);
            $table = $this->reflection->getTable($tableName);
            $type = $table->getType();
            $pk = $table->getPk();
            $pkName = $pk ? $pk->getName() : '';
            foreach (['list', 'read'] as $operation) {
                if (!$pkName && $operation != 'list') {
                    continue;
                }
                if ($type != 'table' && $operation != 'list') {
                    continue;
                }
                if (!$this->isOperationOnTableAllowed($operation, $tableName)) {
                    continue;
                }
                if ($operation == 'list') {
                    $this->openapi->set("components|responses|$operation-$normalizedTableName|description", "list of $tableName records");
                } else {
                    $this->openapi->set("components|responses|$operation-$normalizedTableName|description", "single $tableName record");
                }
                $this->openapi->set("components|responses|$operation-$normalizedTableName|content|application/json|schema|\$ref", "#/components/schemas/$operation-$normalizedTableName");
            }
        }
        private function setComponentRequestBody(string $tableName) /*: void*/
        {
            $normalizedTableName = $this->normalize($tableName);
            $table = $this->reflection->getTable($tableName);
            $type = $table->getType();
            $pk = $table->getPk();
            $pkName = $pk ? $pk->getName() : '';
            if ($pkName && $type == 'table') {
                foreach (['create', 'update', 'increment'] as $operation) {
                    if (!$this->isOperationOnTableAllowed($operation, $tableName)) {
                        continue;
                    }
                    $this->openapi->set("components|requestBodies|$operation-$normalizedTableName|description", "single $tableName record");
                    $this->openapi->set("components|requestBodies|$operation-$normalizedTableName|content|application/json|schema|\$ref", "#/components/schemas/$operation-$normalizedTableName");
                }
            }
        }
        private function setComponentParameters() /*: void*/
        {
            $this->openapi->set("components|parameters|pk|name", "id");
            $this->openapi->set("components|parameters|pk|in", "path");
            $this->openapi->set("components|parameters|pk|schema|type", "string");
            $this->openapi->set("components|parameters|pk|description", "primary key value");
            $this->openapi->set("components|parameters|pk|required", true);
            $this->openapi->set("components|parameters|filter|name", "filter");
            $this->openapi->set("components|parameters|filter|in", "query");
            $this->openapi->set("components|parameters|filter|schema|type", "array");
            $this->openapi->set("components|parameters|filter|schema|items|type", "string");
            $this->openapi->set("components|parameters|filter|description", "Filters to be applied. Each filter consists of a column, an operator and a value (comma separated). Example: id,eq,1");
            $this->openapi->set("components|parameters|filter|required", false);
            $this->openapi->set("components|parameters|include|name", "include");
            $this->openapi->set("components|parameters|include|in", "query");
            $this->openapi->set("components|parameters|include|schema|type", "string");
            $this->openapi->set("components|parameters|include|description", "Columns you want to include in the output (comma separated). Example: posts.*,categories.name");
            $this->openapi->set("components|parameters|include|required", false);
            $this->openapi->set("components|parameters|exclude|name", "exclude");
            $this->openapi->set("components|parameters|exclude|in", "query");
            $this->openapi->set("components|parameters|exclude|schema|type", "string");
            $this->openapi->set("components|parameters|exclude|description", "Columns you want to exclude from the output (comma separated). Example: posts.content");
            $this->openapi->set("components|parameters|exclude|required", false);
            $this->openapi->set("components|parameters|order|name", "order");
            $this->openapi->set("components|parameters|order|in", "query");
            $this->openapi->set("components|parameters|order|schema|type", "array");
            $this->openapi->set("components|parameters|order|schema|items|type", "string");
            $this->openapi->set("components|parameters|order|description", "Column you want to sort on and the sort direction (comma separated). Example: id,desc");
            $this->openapi->set("components|parameters|order|required", false);
            $this->openapi->set("components|parameters|size|name", "size");
            $this->openapi->set("components|parameters|size|in", "query");
            $this->openapi->set("components|parameters|size|schema|type", "string");
            $this->openapi->set("components|parameters|size|description", "Maximum number of results (for top lists). Example: 10");
            $this->openapi->set("components|parameters|size|required", false);
            $this->openapi->set("components|parameters|page|name", "page");
            $this->openapi->set("components|parameters|page|in", "query");
            $this->openapi->set("components|parameters|page|schema|type", "string");
            $this->openapi->set("components|parameters|page|description", "Page number and page size (comma separated). Example: 1,10");
            $this->openapi->set("components|parameters|page|required", false);
            $this->openapi->set("components|parameters|join|name", "join");
            $this->openapi->set("components|parameters|join|in", "query");
            $this->openapi->set("components|parameters|join|schema|type", "array");
            $this->openapi->set("components|parameters|join|schema|items|type", "string");
            $this->openapi->set("components|parameters|join|description", "Paths (comma separated) to related entities that you want to include. Example: comments,users");
            $this->openapi->set("components|parameters|join|required", false);
        }
        private function setTag(string $tableName) /*: void*/
        {
            $this->openapi->set("tags|", ['name' => $tableName, 'description' => "$tableName operations"]);
        }
    }
}
namespace afcodehub\gatewaypro\OpenApi {
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\OpenApi\OpenApiBuilder;
    use afcodehub\gatewaypro\RequestFactory;
    class OpenApiService
    {
        private $builder;
        public function __construct(ReflectionService $reflection, array $base, array $controllers, array $customBuilders)
        {
            $this->builder = new OpenApiBuilder($reflection, $base, $controllers, $customBuilders);
        }
        public function get(ServerRequestInterface $request): OpenApiDefinition
        {
            return $this->builder->build(RequestFactory::fromGlobals());
        }
    }
}
namespace afcodehub\gatewaypro\OpenApi {
    use afcodehub\gatewaypro\OpenApi\OpenApiDefinition;
    class OpenApiStatusBuilder
    {
        private $openapi;
        private $operations = [
            'status' => [
                'ping' => 'get',
            ],
        ];
        public function __construct(OpenApiDefinition $openapi)
        {
            $this->openapi = $openapi;
        }
        public function build() /*: void*/
        {
            $this->setPaths();
            $this->setComponentSchema();
            $this->setComponentResponse();
            foreach (array_keys($this->operations) as $type) {
                $this->setTag($type);
            }
        }
        private function setPaths() /*: void*/
        {
            foreach ($this->operations as $type => $operationPair) {
                foreach ($operationPair as $operation => $method) {
                    $path = "/$type/$operation";
                    $this->openapi->set("paths|$path|$method|tags|", "$type");
                    $this->openapi->set("paths|$path|$method|operationId", "$operation" . "_" . "$type");
                    $this->openapi->set("paths|$path|$method|description", "Request API '$operation' status");
                    $this->openapi->set("paths|$path|$method|responses|200|\$ref", "#/components/responses/$operation-$type");
                }
            }
        }
        private function setComponentSchema() /*: void*/
        {
            foreach ($this->operations as $type => $operationPair) {
                foreach ($operationPair as $operation => $method) {
                    $prefix = "components|schemas|$operation-$type";
                    $this->openapi->set("$prefix|type", "object");
                    switch ($operation) {
                        case 'ping':
                            $this->openapi->set("$prefix|required", ['db', 'cache']);
                            $this->openapi->set("$prefix|properties|db|type", 'integer');
                            $this->openapi->set("$prefix|properties|db|format", "int64");
                            $this->openapi->set("$prefix|properties|cache|type", 'integer');
                            $this->openapi->set("$prefix|properties|cache|format", "int64");
                            break;
                    }
                }
            }
        }
        private function setComponentResponse() /*: void*/
        {
            foreach ($this->operations as $type => $operationPair) {
                foreach ($operationPair as $operation => $method) {
                    $this->openapi->set("components|responses|$operation-$type|description", "$operation status record");
                    $this->openapi->set("components|responses|$operation-$type|content|application/json|schema|\$ref", "#/components/schemas/$operation-$type");
                }
            }
        }
        private function setTag(string $type) /*: void*/
        {
            $this->openapi->set("tags|", [ 'name' => $type, 'description' => "$type operations"]);
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    class AndCondition extends Condition
    {
        private $conditions;
        public function __construct(Condition $condition1, Condition $condition2)
        {
            $this->conditions = [$condition1, $condition2];
        }
        public function _and(Condition $condition): Condition
        {
            if ($condition instanceof NoCondition) {
                return $this;
            }
            $this->conditions[] = $condition;
            return $this;
        }
        public function getConditions(): array
        {
            return $this->conditions;
        }
        public static function fromArray(array $conditions): Condition
        {
            $condition = new NoCondition();
            foreach ($conditions as $c) {
                $condition = $condition->_and($c);
            }
            return $condition;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedColumn;
    class ColumnCondition extends Condition
    {
        private $column;
        private $operator;
        private $value;
        public function __construct(ReflectedColumn $column, string $operator, string $value)
        {
            $this->column = $column;
            $this->operator = $operator;
            $this->value = $value;
        }
        public function getColumn(): ReflectedColumn
        {
            return $this->column;
        }
        public function getOperator(): string
        {
            return $this->operator;
        }
        public function getValue(): string
        {
            return $this->value;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    abstract class Condition
    {
        public function _and(Condition $condition): Condition
        {
            if ($condition instanceof NoCondition) {
                return $this;
            }
            return new AndCondition($this, $condition);
        }
        public function _or(Condition $condition): Condition
        {
            if ($condition instanceof NoCondition) {
                return $this;
            }
            return new OrCondition($this, $condition);
        }
        public function _not(): Condition
        {
            return new NotCondition($this);
        }
        public static function fromString(ReflectedTable $table, string $value): Condition
        {
            $condition = new NoCondition();
            $parts = explode(',', $value, 3);
            if (count($parts) < 2) {
                return $condition;
            }
            if (count($parts) < 3) {
                $parts[2] = '';
            }
            $field = $table->getColumn($parts[0]);
            $command = $parts[1];
            $negate = false;
            $spatial = false;
            if (strlen($command) > 2) {
                if (substr($command, 0, 1) == 'n') {
                    $negate = true;
                    $command = substr($command, 1);
                } else if (substr($command, 0, 1) == 's') {
                    $spatial = true;
                    $command = substr($command, 1);
                }
            }
            if ($spatial) {
                if (in_array($command, ['co', 'cr', 'di', 'eq', 'in', 'ov', 'to', 'wi', 'ic', 'is', 'iv'])) {
                    $condition = new SpatialCondition($field, $command, $parts[2]);
                }
            } else {
                if (in_array($command, ['cs', 'sw', 'ew', 'eq', 'lt', 'le', 'ge', 'gt', 'bt', 'in', 'is'])) {
                    $condition = new ColumnCondition($field, $command, $parts[2]);
                }
            }
            if ($negate) {
                $condition = $condition->_not();
            }
            return $condition;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    class NoCondition extends Condition
    {
        public function _and(Condition $condition): Condition
        {
            return $condition;
        }
        public function _or(Condition $condition): Condition
        {
            return $condition;
        }
        public function _not(): Condition
        {
            return $this;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    class NotCondition extends Condition
    {
        private $condition;
        public function __construct(Condition $condition)
        {
            $this->condition = $condition;
        }
        public function getCondition(): Condition
        {
            return $this->condition;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    class OrCondition extends Condition
    {
        private $conditions;
        public function __construct(Condition $condition1, Condition $condition2)
        {
            $this->conditions = [$condition1, $condition2];
        }
        public function _or(Condition $condition): Condition
        {
            if ($condition instanceof NoCondition) {
                return $this;
            }
            $this->conditions[] = $condition;
            return $this;
        }
        public function getConditions(): array
        {
            return $this->conditions;
        }
        public static function fromArray(array $conditions): Condition
        {
            $condition = new NoCondition();
            foreach ($conditions as $c) {
                $condition = $condition->_or($c);
            }
            return $condition;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Condition {
    class SpatialCondition extends ColumnCondition
    {
    }
}
namespace afcodehub\gatewaypro\Record\Document {
    use afcodehub\gatewaypro\Record\ErrorCode;
    class ErrorDocument implements \JsonSerializable
    {
        public $errorCode;
        public $argument;
        public $details;
        public function __construct(ErrorCode $errorCode, string $argument, $details)
        {
            $this->errorCode = $errorCode;
            $this->argument = $argument;
            $this->details = $details;
        }
        public function getStatus(): int
        {
            return $this->errorCode->getStatus();
        }
        public function getCode(): int
        {
            return $this->errorCode->getCode();
        }
        public function getMessage(): string
        {
            return $this->errorCode->getMessage($this->argument);
        }
        public function serialize()
        {
            return [
                'code' => $this->getCode(),
                'message' => $this->getMessage(),
                'details' => $this->details,
            ];
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return array_filter($this->serialize(), function ($v) {return $v !== null;});
        }
        public static function fromException(\Throwable $exception, bool $debug)
        {
            $document = new ErrorDocument(new ErrorCode(ErrorCode::ERROR_NOT_FOUND), $exception->getMessage(), null);
            if ($exception instanceof \PDOException) {
                if (strpos(strtolower($exception->getMessage()), 'duplicate') !== false) {
                    $document = new ErrorDocument(new ErrorCode(ErrorCode::DUPLICATE_KEY_EXCEPTION), '', null);
                } elseif (strpos(strtolower($exception->getMessage()), 'unique constraint') !== false) {
                    $document = new ErrorDocument(new ErrorCode(ErrorCode::DUPLICATE_KEY_EXCEPTION), '', null);
                } elseif (strpos(strtolower($exception->getMessage()), 'default value') !== false) {
                    $document = new ErrorDocument(new ErrorCode(ErrorCode::DATA_INTEGRITY_VIOLATION), '', null);
                } elseif (strpos(strtolower($exception->getMessage()), 'allow nulls') !== false) {
                    $document = new ErrorDocument(new ErrorCode(ErrorCode::DATA_INTEGRITY_VIOLATION), '', null);
                } elseif (strpos(strtolower($exception->getMessage()), 'constraint') !== false) {
                    $document = new ErrorDocument(new ErrorCode(ErrorCode::DATA_INTEGRITY_VIOLATION), '', null);
                } else {
                    $message = $debug ? $exception->getMessage() : 'PDOException occurred (enable debug mode)';
                    $document = new ErrorDocument(new ErrorCode(ErrorCode::ERROR_NOT_FOUND), $message, null);
                }
            }
            return $document;
        }
    }
}
namespace afcodehub\gatewaypro\Record\Document {
    class ListDocument implements \JsonSerializable
    {
        private $records;
        private $results;
        public function __construct(array $records, int $results)
        {
            $this->records = $records;
            $this->results = $results;
        }
        public function getRecords(): array
        {
            return $this->records;
        }
        public function getResults(): int
        {
            return $this->results;
        }
        public function serialize()
        {
            return [
                'records' => $this->records,
                'results' => $this->results,
            ];
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return array_filter($this->serialize(), function ($v) {
                return $v !== -1;
            });
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    class ColumnIncluder
    {
        private function isMandatory(string $tableName, string $columnName, array $params): bool
        {
            return isset($params['mandatory']) && in_array($tableName . "." . $columnName, $params['mandatory']);
        }
        private function select(
            string $tableName,
            bool $primaryTable,
            array $params,
            string $paramName,
            array $columnNames,
            bool $include
        ): array {
            if (!isset($params[$paramName])) {
                return $columnNames;
            }
            $columns = array();
            foreach (explode(',', $params[$paramName][0]) as $columnName) {
                $columns[$columnName] = true;
            }
            $result = array();
            foreach ($columnNames as $columnName) {
                $match = isset($columns['*.*']);
                if (!$match) {
                    $match = isset($columns[$tableName . '.*']) || isset($columns[$tableName . '.' . $columnName]);
                }
                if ($primaryTable && !$match) {
                    $match = isset($columns['*']) || isset($columns[$columnName]);
                }
                if ($match) {
                    if ($include || $this->isMandatory($tableName, $columnName, $params)) {
                        $result[] = $columnName;
                    }
                } else {
                    if (!$include || $this->isMandatory($tableName, $columnName, $params)) {
                        $result[] = $columnName;
                    }
                }
            }
            return $result;
        }
        public function getNames(ReflectedTable $table, bool $primaryTable, array $params): array
        {
            $tableName = $table->getName();
            $results = $table->getColumnNames();
            $results = $this->select($tableName, $primaryTable, $params, 'include', $results, true);
            $results = $this->select($tableName, $primaryTable, $params, 'exclude', $results, false);
            return $results;
        }
        public function getValues(ReflectedTable $table, bool $primaryTable, /* object */ $record, array $params): array
        {
            $results = array();
            $columnNames = $this->getNames($table, $primaryTable, $params);
            foreach ($columnNames as $columnName) {
                if (property_exists($record, $columnName)) {
                    $results[$columnName] = $record->$columnName;
                }
            }
            return $results;
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    use afcodehub\gatewaypro\ResponseFactory;
    class ErrorCode
    {
        private $code;
        private $message;
        private $status;
        const ERROR_NOT_FOUND = 9999;
        const ROUTE_NOT_FOUND = 1000;
        const TABLE_NOT_FOUND = 1001;
        const ARGUMENT_COUNT_MISMATCH = 1002;
        const RECORD_NOT_FOUND = 1003;
        const ORIGIN_FORBIDDEN = 1004;
        const COLUMN_NOT_FOUND = 1005;
        const TABLE_ALREADY_EXISTS = 1006;
        const COLUMN_ALREADY_EXISTS = 1007;
        const HTTP_MESSAGE_NOT_READABLE = 1008;
        const DUPLICATE_KEY_EXCEPTION = 1009;
        const DATA_INTEGRITY_VIOLATION = 1010;
        const AUTHENTICATION_REQUIRED = 1011;
        const AUTHENTICATION_FAILED = 1012;
        const INPUT_VALIDATION_FAILED = 1013;
        const OPERATION_FORBIDDEN = 1014;
        const OPERATION_NOT_SUPPORTED = 1015;
        const TEMPORARY_OR_PERMANENTLY_BLOCKED = 1016;
        const BAD_OR_MISSING_XSRF_TOKEN = 1017;
        const ONLY_AJAX_REQUESTS_ALLOWED = 1018;
        const PAGINATION_FORBIDDEN = 1019;
        const USER_ALREADY_EXIST = 1020;
        const PASSWORD_TOO_SHORT = 1021;
        const USERNAME_EMPTY = 1022;
        const PRIMARY_KEY_NOT_FOUND = 1023;
        private $values = [
            0000 => ["Sucesso", ResponseFactory::OK],
            1000 => ["Rota '%s' não encontrada", ResponseFactory::NOT_FOUND],
            1001 => ["Tabela '%s' não encontrada", ResponseFactory::NOT_FOUND],
            1002 => ["Quantidade de argumentos incompatível em '%s'", ResponseFactory::UNPROCESSABLE_ENTITY],
            1003 => ["Registro '%s' não encontrado", ResponseFactory::NOT_FOUND],
            1004 => ["Origem '%s' é proibida", ResponseFactory::FORBIDDEN],
            1005 => ["Coluna '%s' não encontrada", ResponseFactory::NOT_FOUND],
            1006 => ["Tabela '%s' já existe", ResponseFactory::CONFLICT],
            1007 => ["Coluna '%s' já existe", ResponseFactory::CONFLICT],
            1008 => ["Não foi possível ler a mensagem HTTP", ResponseFactory::UNPROCESSABLE_ENTITY],
            1009 => ["Exceção de chave duplicada", ResponseFactory::CONFLICT],
            1010 => ["Violação de integridade de dados", ResponseFactory::CONFLICT],
            1011 => ["Autenticação necessária", ResponseFactory::UNAUTHORIZED],
            1012 => ["Falha de autenticação '%s'", ResponseFactory::FORBIDDEN],
            1013 => ["Falha na validação de entrada '%s'", ResponseFactory::UNPROCESSABLE_ENTITY],
            1014 => ["Operação proibida", ResponseFactory::FORBIDDEN],
            1015 => ["Operação '%s' não suportada", ResponseFactory::METHOD_NOT_ALLOWED],
            1016 => ["Bloqueado temporária ou permanentemente", ResponseFactory::FORBIDDEN],
            1017 => ["Token XSRF inválido ou ausente", ResponseFactory::FORBIDDEN],
            1018 => ["Apenas requisições AJAX são permitidas para '%s'", ResponseFactory::FORBIDDEN],
            1019 => ["Paginação proibida", ResponseFactory::FORBIDDEN],
            1020 => ["Usuário '%s' já existe", ResponseFactory::CONFLICT],
            1021 => ["Senha muito curta (<%d caracteres)", ResponseFactory::UNPROCESSABLE_ENTITY],
            1022 => ["Nome de usuário está vazio ou contém apenas espaços", ResponseFactory::UNPROCESSABLE_ENTITY],
            1023 => ["Chave primária para a tabela '%s' não encontrada", ResponseFactory::NOT_FOUND],           
            9999 => ["%s", ResponseFactory::INTERNAL_SERVER_ERROR],
        ];
        public function __construct(int $code)
        {
            if (!isset($this->values[$code])) {
                $code = 9999;
            }
            $this->code = $code;
            $this->message = $this->values[$code][0];
            $this->status = $this->values[$code][1];
        }
        public function getCode(): int
        {
            return $this->code;
        }
        public function getMessage(string $argument): string
        {
            return sprintf($this->message, $argument);
        }
        public function getStatus(): int
        {
            return $this->status;
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Record\Condition\AndCondition;
    use afcodehub\gatewaypro\Record\Condition\Condition;
    use afcodehub\gatewaypro\Record\Condition\NoCondition;
    use afcodehub\gatewaypro\Record\Condition\OrCondition;
    class FilterInfo
    {
        private function getConditionsAsPathTree(ReflectedTable $table, array $params): PathTree
        {
            $conditions = new PathTree();
            foreach ($params as $key => $filters) {
                if (substr($key, 0, 6) == 'filter') {
                    preg_match_all('/\d+|\D+/', substr($key, 6), $matches);
                    $path = $matches[0];
                    foreach ($filters as $filter) {
                        $condition = Condition::fromString($table, $filter);
                        if (($condition instanceof NoCondition) == false) {
                            $conditions->put($path, $condition);
                        }
                    }
                }
            }
            return $conditions;
        }
        private function combinePathTreeOfConditions(PathTree $tree): Condition
        {
            $andConditions = $tree->getValues();
            $and = AndCondition::fromArray($andConditions);
            $orConditions = [];
            foreach ($tree->getKeys() as $p) {
                $orConditions[] = $this->combinePathTreeOfConditions($tree->get($p));
            }
            $or = OrCondition::fromArray($orConditions);
            return $and->_and($or);
        }
        public function getCombinedConditions(ReflectedTable $table, array $params): Condition
        {
            return $this->combinePathTreeOfConditions($this->getConditionsAsPathTree($table, $params));
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    class HabtmValues
    {
        public $pkValues;
        public $fkValues;
        public function __construct(array $pkValues, array $fkValues)
        {
            $this->pkValues = $pkValues;
            $this->fkValues = $fkValues;
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    class OrderingInfo
    {
        public function getColumnOrdering(ReflectedTable $table, array $params): array
        {
            $fields = array();
            if (isset($params['order'])) {
                foreach ($params['order'] as $order) {
                    $parts = explode(',', $order, 3);
                    $columnName = $parts[0];
                    if (!$table->hasColumn($columnName)) {
                        continue;
                    }
                    $ascending = 'ASC';
                    if (count($parts) > 1) {
                        if (substr(strtoupper($parts[1]), 0, 4) == "DESC") {
                            $ascending = 'DESC';
                        }
                    }
                    $fields[] = [$columnName, $ascending];
                }
            }
            if (count($fields) == 0) {
                return $this->getDefaultColumnOrdering($table);
            }
            return $fields;
        }
        public function getDefaultColumnOrdering(ReflectedTable $table): array
        {
            $fields = array();
            $pk = $table->getPk();
            if ($pk) {
                $fields[] = [$pk->getName(), 'ASC'];
            } else {
                foreach ($table->getColumnNames() as $columnName) {
                    $fields[] = [$columnName, 'ASC'];
                }
            }
            return $fields;
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    class PaginationInfo
    {
        public $DEFAULT_PAGE_SIZE = 20;
        public function hasPage(array $params): bool
        {
            return isset($params['page']);
        }
        public function getPageOffset(array $params): int
        {
            $offset = 0;
            $pageSize = $this->getPageSize($params);
            if (isset($params['page'])) {
                foreach ($params['page'] as $page) {
                    $parts = explode(',', $page, 2);
                    $page = intval($parts[0]) - 1;
                    $offset = $page * $pageSize;
                }
            }
            return $offset;
        }
        private function getPageSize(array $params): int
        {
            $pageSize = $this->DEFAULT_PAGE_SIZE;
            if (isset($params['page'])) {
                foreach ($params['page'] as $page) {
                    $parts = explode(',', $page, 2);
                    if (count($parts) > 1) {
                        $pageSize = intval($parts[1]);
                    }
                }
            }
            return $pageSize;
        }
        public function getResultSize(array $params): int
        {
            $numberOfRows = -1;
            if (isset($params['size'])) {
                foreach ($params['size'] as $size) {
                    $numberOfRows = intval($size);
                }
            }
            return $numberOfRows;
        }
        public function getPageLimit(array $params): int
        {
            $pageLimit = -1;
            if ($this->hasPage($params)) {
                $pageLimit = $this->getPageSize($params);
            }
            $resultSize = $this->getResultSize($params);
            if ($resultSize >= 0) {
                if ($pageLimit >= 0) {
                    $pageLimit = min($pageLimit, $resultSize);
                } else {
                    $pageLimit = $resultSize;
                }
            }
            return $pageLimit;
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    class PathTree implements \JsonSerializable
    {
        const WILDCARD = '*';
        private $tree;
        public function __construct( /* object */&$tree = null)
        {
            if (!$tree) {
                $tree = $this->newTree();
            }
            $this->tree = &$tree;
        }
        public function newTree()
        {
            return (object) ['values' => [], 'branches' => (object) []];
        }
        public function getKeys(): array
        {
            $branches = (array) $this->tree->branches;
            return array_keys($branches);
        }
        public function getValues(): array
        {
            return $this->tree->values;
        }
        public function get(string $key): PathTree
        {
            if (!isset($this->tree->branches->$key)) {
                return null;
            }
            return new PathTree($this->tree->branches->$key);
        }
        public function put(array $path, $value)
        {
            $tree = &$this->tree;
            foreach ($path as $key) {
                if (!isset($tree->branches->$key)) {
                    $tree->branches->$key = $this->newTree();
                }
                $tree = &$tree->branches->$key;
            }
            $tree->values[] = $value;
        }
        public function match(array $path): array
        {
            $star = self::WILDCARD;
            $tree = &$this->tree;
            foreach ($path as $key) {
                if (isset($tree->branches->$key)) {
                    $tree = &$tree->branches->$key;
                } elseif (isset($tree->branches->$star)) {
                    $tree = &$tree->branches->$star;
                } else {
                    return [];
                }
            }
            return $tree->values;
        }
        public static function fromJson( /* object */$tree): PathTree
        {
            return new PathTree($tree);
        }
        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->tree;
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Database\GenericDB;
    use afcodehub\gatewaypro\Record\Document\ListDocument;
    class RecordService
    {
        private $db;
        private $reflection;
        private $columns;
        private $joiner;
        private $filters;
        private $ordering;
        private $pagination;
        public function __construct(GenericDB $db, ReflectionService $reflection)
        {
            $this->db = $db;
            $this->reflection = $reflection;
            $this->columns = new ColumnIncluder();
            $this->joiner = new RelationJoiner($reflection, $this->columns);
            $this->filters = new FilterInfo();
            $this->ordering = new OrderingInfo();
            $this->pagination = new PaginationInfo();
        }
        private function sanitizeRecord(string $tableName, /* object */ $record, string $id)
        {
            $keyset = array_keys((array) $record);
            foreach ($keyset as $key) {
                if (!$this->reflection->getTable($tableName)->hasColumn($key)) {
                    unset($record->$key);
                }
            }
            if ($id != '') {
                $pk = $this->reflection->getTable($tableName)->getPk();
                foreach ($this->reflection->getTable($tableName)->getColumnNames() as $key) {
                    $field = $this->reflection->getTable($tableName)->getColumn($key);
                    if ($field->getName() == $pk->getName()) {
                        unset($record->$key);
                    }
                }
            }
        }
        public function hasTable(string $table): bool
        {
            return $this->reflection->hasTable($table);
        }
        public function hasPrimaryKey(string $table): bool
        {
            return $this->reflection->getTable($table)->getPk()?true:false;
        }
        public function getType(string $table): string
        {
            return $this->reflection->getType($table);
        }
        public function beginTransaction() /*: void*/
        {
            $this->db->beginTransaction();
        }
        public function commitTransaction() /*: void*/
        {
            $this->db->commitTransaction();
        }
        public function rollBackTransaction() /*: void*/
        {
            $this->db->rollBackTransaction();
        }
        public function create(string $tableName, /* object */ $record, array $params) /*: ?int*/
        {
            $this->sanitizeRecord($tableName, $record, '');
            $table = $this->reflection->getTable($tableName);
            $columnValues = $this->columns->getValues($table, true, $record, $params);
            return $this->db->createSingle($table, $columnValues);
        }
        public function read(string $tableName, string $id, array $params) /*: ?object*/
        {
            $table = $this->reflection->getTable($tableName);
            $this->joiner->addMandatoryColumns($table, $params);
            $columnNames = $this->columns->getNames($table, true, $params);
            $record = $this->db->selectSingle($table, $columnNames, $id);
            if ($record == null) {
                return null;
            }
            $records = array($record);
            $this->joiner->addJoins($table, $records, $params, $this->db);
            return $records[0];
        }
        public function update(string $tableName, string $id, /* object */ $record, array $params) /*: ?int*/
        {
            $this->sanitizeRecord($tableName, $record, $id);
            $table = $this->reflection->getTable($tableName);
            $columnValues = $this->columns->getValues($table, true, $record, $params);
            return $this->db->updateSingle($table, $columnValues, $id);
        }
        public function delete(string $tableName, string $id, array $params) /*: ?int*/
        {
            $table = $this->reflection->getTable($tableName);
            return $this->db->deleteSingle($table, $id);
        }
        public function increment(string $tableName, string $id, /* object */ $record, array $params) /*: ?int*/
        {
            $this->sanitizeRecord($tableName, $record, $id);
            $table = $this->reflection->getTable($tableName);
            $columnValues = $this->columns->getValues($table, true, $record, $params);
            return $this->db->incrementSingle($table, $columnValues, $id);
        }
        public function _list(string $tableName, array $params): ListDocument
        {
            $table = $this->reflection->getTable($tableName);
            $this->joiner->addMandatoryColumns($table, $params);
            $columnNames = $this->columns->getNames($table, true, $params);
            $condition = $this->filters->getCombinedConditions($table, $params);
            $columnOrdering = $this->ordering->getColumnOrdering($table, $params);
            if (!$this->pagination->hasPage($params)) {
                $offset = 0;
                $limit = $this->pagination->getPageLimit($params);
                $count = -1;
            } else {
                $offset = $this->pagination->getPageOffset($params);
                $limit = $this->pagination->getPageLimit($params);
                $count = $this->db->selectCount($table, $condition);
            }
            $records = $this->db->selectAll($table, $columnNames, $condition, $columnOrdering, $offset, $limit);
            $this->joiner->addJoins($table, $records, $params, $this->db);
            return new ListDocument($records, $count);
        }
        public function ping(): int
        {
            return $this->db->ping();
        }
    }
}
namespace afcodehub\gatewaypro\Record {
    use afcodehub\gatewaypro\Column\Reflection\ReflectedTable;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Database\GenericDB;
    use afcodehub\gatewaypro\Middleware\Communication\VariableStore;
    use afcodehub\gatewaypro\Record\Condition\ColumnCondition;
    use afcodehub\gatewaypro\Record\Condition\OrCondition;
    class RelationJoiner
    {
        private $reflection;
        private $ordering;
        private $columns;
        public function __construct(ReflectionService $reflection, ColumnIncluder $columns)
        {
            $this->reflection = $reflection;
            $this->ordering = new OrderingInfo();
            $this->columns = $columns;
        }
        public function addMandatoryColumns(ReflectedTable $table, array &$params) /*: void*/
        {
            if (!isset($params['join']) || !isset($params['include'])) {
                return;
            }
            $params['mandatory'] = array();
            foreach ($params['join'] as $tableNames) {
                $t1 = $table;
                foreach (explode(',', $tableNames) as $tableName) {
                    if (!$this->reflection->hasTable($tableName)) {
                        continue;
                    }
                    $t2 = $this->reflection->getTable($tableName);
                    $fks1 = $t1->getFksTo($t2->getName());
                    $t3 = $this->hasAndBelongsToMany($t1, $t2);
                    if ($t3 != null || count($fks1) > 0) {
                        $params['mandatory'][] = $t2->getName() . '.' . $t2->getPk()->getName();
                    }
                    foreach ($fks1 as $fk) {
                        $params['mandatory'][] = $t1->getName() . '.' . $fk->getName();
                    }
                    $fks2 = $t2->getFksTo($t1->getName());
                    if ($t3 != null || count($fks2) > 0) {
                        $params['mandatory'][] = $t1->getName() . '.' . $t1->getPk()->getName();
                    }
                    foreach ($fks2 as $fk) {
                        $params['mandatory'][] = $t2->getName() . '.' . $fk->getName();
                    }
                    $t1 = $t2;
                }
            }
        }
        private function getJoinsAsPathTree(array $params): PathTree
        {
            $joins = new PathTree();
            if (isset($params['join'])) {
                foreach ($params['join'] as $tableNames) {
                    $path = array();
                    foreach (explode(',', $tableNames) as $tableName) {
                        if (!$this->reflection->hasTable($tableName)) {
                            continue;
                        }
                        $t = $this->reflection->getTable($tableName);
                        if ($t != null) {
                            $path[] = $t->getName();
                        }
                    }
                    $joins->put($path, true);
                }
            }
            return $joins;
        }
        public function addJoins(ReflectedTable $table, array &$records, array $params, GenericDB $db) /*: void*/
        {
            $joins = $this->getJoinsAsPathTree($params);
            $this->addJoinsForTables($table, $joins, $records, $params, $db);
        }
        private function hasAndBelongsToMany(ReflectedTable $t1, ReflectedTable $t2) /*: ?ReflectedTable*/
        {
            foreach ($this->reflection->getTableNames() as $tableName) {
                $t3 = $this->reflection->getTable($tableName);
                if (count($t3->getFksTo($t1->getName())) > 0 && count($t3->getFksTo($t2->getName())) > 0) {
                    return $t3;
                }
            }
            return null;
        }
        private function addJoinsForTables(ReflectedTable $t1, PathTree $joins, array &$records, array $params, GenericDB $db)
        {
            foreach ($joins->getKeys() as $t2Name) {
                $t2 = $this->reflection->getTable($t2Name);
                $belongsTo = count($t1->getFksTo($t2->getName())) > 0;
                $hasMany = count($t2->getFksTo($t1->getName())) > 0;
                if (!$belongsTo && !$hasMany) {
                    $t3 = $this->hasAndBelongsToMany($t1, $t2);
                } else {
                    $t3 = null;
                }
                $hasAndBelongsToMany = ($t3 != null);
                $newRecords = array();
                $fkValues = null;
                $pkValues = null;
                $habtmValues = null;
                if ($belongsTo) {
                    $fkValues = $this->getFkEmptyValues($t1, $t2, $records);
                    $this->addFkRecords($t2, $fkValues, $params, $db, $newRecords);
                }
                if ($hasMany) {
                    $pkValues = $this->getPkEmptyValues($t1, $records);
                    $this->addPkRecords($t1, $t2, $pkValues, $params, $db, $newRecords);
                }
                if ($hasAndBelongsToMany) {
                    $habtmValues = $this->getHabtmEmptyValues($t1, $t2, $t3, $db, $records);
                    $this->addFkRecords($t2, $habtmValues->fkValues, $params, $db, $newRecords);
                }
                $this->addJoinsForTables($t2, $joins->get($t2Name), $newRecords, $params, $db);
                if ($fkValues != null) {
                    $this->fillFkValues($t2, $newRecords, $fkValues);
                    $this->setFkValues($t1, $t2, $records, $fkValues);
                }
                if ($pkValues != null) {
                    $this->fillPkValues($t1, $t2, $newRecords, $pkValues);
                    $this->setPkValues($t1, $t2, $records, $pkValues);
                }
                if ($habtmValues != null) {
                    $this->fillFkValues($t2, $newRecords, $habtmValues->fkValues);
                    $this->setHabtmValues($t1, $t2, $records, $habtmValues);
                }
            }
        }
        private function getFkEmptyValues(ReflectedTable $t1, ReflectedTable $t2, array $records): array
        {
            $fkValues = array();
            $fks = $t1->getFksTo($t2->getName());
            foreach ($fks as $fk) {
                $fkName = $fk->getName();
                foreach ($records as $record) {
                    if (isset($record[$fkName])) {
                        $fkValue = $record[$fkName];
                        $fkValues[$fkValue] = null;
                    }
                }
            }
            return $fkValues;
        }
        private function addFkRecords(ReflectedTable $t2, array $fkValues, array $params, GenericDB $db, array &$records) /*: void*/
        {
            $columnNames = $this->columns->getNames($t2, false, $params);
            $fkIds = array_keys($fkValues);
            foreach ($db->selectMultiple($t2, $columnNames, $fkIds) as $record) {
                $records[] = $record;
            }
        }
        private function fillFkValues(ReflectedTable $t2, array $fkRecords, array &$fkValues) /*: void*/
        {
            $pkName = $t2->getPk()->getName();
            foreach ($fkRecords as $fkRecord) {
                $pkValue = $fkRecord[$pkName];
                $fkValues[$pkValue] = $fkRecord;
            }
        }
        private function setFkValues(ReflectedTable $t1, ReflectedTable $t2, array &$records, array $fkValues) /*: void*/
        {
            $fks = $t1->getFksTo($t2->getName());
            foreach ($fks as $fk) {
                $fkName = $fk->getName();
                foreach ($records as $i => $record) {
                    if (isset($record[$fkName])) {
                        $key = $record[$fkName];
                        $records[$i][$fkName] = $fkValues[$key];
                    }
                }
            }
        }
        private function getPkEmptyValues(ReflectedTable $t1, array $records): array
        {
            $pkValues = array();
            $pkName = $t1->getPk()->getName();
            foreach ($records as $record) {
                $key = $record[$pkName];
                $pkValues[$key] = array();
            }
            return $pkValues;
        }
        private function addPkRecords(ReflectedTable $t1, ReflectedTable $t2, array $pkValues, array $params, GenericDB $db, array &$records) /*: void*/
        {
            $fks = $t2->getFksTo($t1->getName());
            $columnNames = $this->columns->getNames($t2, false, $params);
            $pkValueKeys = implode(',', array_keys($pkValues));
            $conditions = array();
            foreach ($fks as $fk) {
                $conditions[] = new ColumnCondition($fk, 'in', $pkValueKeys);
            }
            $condition = OrCondition::fromArray($conditions);
            $columnOrdering = array();
            $limit = VariableStore::get("joinLimits.maxRecords") ?: -1;
            if ($limit != -1) {
                $columnOrdering = $this->ordering->getDefaultColumnOrdering($t2);
            }
            foreach ($db->selectAll($t2, $columnNames, $condition, $columnOrdering, 0, $limit) as $record) {
                $records[] = $record;
            }
        }
        private function fillPkValues(ReflectedTable $t1, ReflectedTable $t2, array $pkRecords, array &$pkValues) /*: void*/
        {
            $fks = $t2->getFksTo($t1->getName());
            foreach ($fks as $fk) {
                $fkName = $fk->getName();
                foreach ($pkRecords as $pkRecord) {
                    $key = $pkRecord[$fkName];
                    if (isset($pkValues[$key])) {
                        $pkValues[$key][] = $pkRecord;
                    }
                }
            }
        }
        private function setPkValues(ReflectedTable $t1, ReflectedTable $t2, array &$records, array $pkValues) /*: void*/
        {
            $pkName = $t1->getPk()->getName();
            $t2Name = $t2->getName();
            foreach ($records as $i => $record) {
                $key = $record[$pkName];
                $records[$i][$t2Name] = $pkValues[$key];
            }
        }
        private function getHabtmEmptyValues(ReflectedTable $t1, ReflectedTable $t2, ReflectedTable $t3, GenericDB $db, array $records): HabtmValues
        {
            $pkValues = $this->getPkEmptyValues($t1, $records);
            $fkValues = array();
            $fk1 = $t3->getFksTo($t1->getName())[0];
            $fk2 = $t3->getFksTo($t2->getName())[0];
            $fk1Name = $fk1->getName();
            $fk2Name = $fk2->getName();
            $columnNames = array($fk1Name, $fk2Name);
            $pkIds = implode(',', array_keys($pkValues));
            $condition = new ColumnCondition($t3->getColumn($fk1Name), 'in', $pkIds);
            $columnOrdering = array();
            $limit = VariableStore::get("joinLimits.maxRecords") ?: -1;
            if ($limit != -1) {
                $columnOrdering = $this->ordering->getDefaultColumnOrdering($t3);
            }
            $records = $db->selectAll($t3, $columnNames, $condition, $columnOrdering, 0, $limit);
            foreach ($records as $record) {
                $val1 = $record[$fk1Name];
                $val2 = $record[$fk2Name];
                $pkValues[$val1][] = $val2;
                $fkValues[$val2] = null;
            }
            return new HabtmValues($pkValues, $fkValues);
        }
        private function setHabtmValues(ReflectedTable $t1, ReflectedTable $t2, array &$records, HabtmValues $habtmValues) /*: void*/
        {
            $pkName = $t1->getPk()->getName();
            $t2Name = $t2->getName();
            foreach ($records as $i => $record) {
                $key = $record[$pkName];
                $val = array();
                $fks = $habtmValues->pkValues[$key];
                foreach ($fks as $fk) {
                    $val[] = $habtmValues->fkValues[$fk];
                }
                $records[$i][$t2Name] = $val;
            }
        }
    }
}
namespace afcodehub\gatewaypro {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use afcodehub\gatewaypro\Cache\CacheFactory;
    use afcodehub\gatewaypro\Column\DefinitionService;
    use afcodehub\gatewaypro\Column\ReflectionService;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\Controller\CacheController;
    use afcodehub\gatewaypro\Controller\ColumnController;
    use afcodehub\gatewaypro\Controller\GeoJsonController;
    use afcodehub\gatewaypro\Controller\JsonResponder;
    use afcodehub\gatewaypro\Controller\OpenApiController;
    use afcodehub\gatewaypro\Controller\RecordController;
    use afcodehub\gatewaypro\Controller\StatusController;
    use afcodehub\gatewaypro\Database\GenericDB;
    use afcodehub\gatewaypro\GeoJson\GeoJsonService;
    use afcodehub\gatewaypro\Middleware\TokenAuthMiddleware;
    use afcodehub\gatewaypro\Middleware\ApiKeyDbAuthMiddleware;
    use afcodehub\gatewaypro\Middleware\AuthorizationMiddleware;
    use afcodehub\gatewaypro\Middleware\BasicAuthMiddleware;
    use afcodehub\gatewaypro\Middleware\CorsMiddleware;
    use afcodehub\gatewaypro\Middleware\CustomizationMiddleware;
    use afcodehub\gatewaypro\Middleware\DbAuthMiddleware;
    use afcodehub\gatewaypro\Middleware\FirewallMiddleware;
    use afcodehub\gatewaypro\Middleware\IpAddressMiddleware;
    use afcodehub\gatewaypro\Middleware\JoinLimitsMiddleware;
    use afcodehub\gatewaypro\Middleware\JsonMiddleware;
    use afcodehub\gatewaypro\Middleware\JwtAuthMiddleware;
    use afcodehub\gatewaypro\Middleware\MultiTenancyMiddleware;
    use afcodehub\gatewaypro\Middleware\PageLimitsMiddleware;
    use afcodehub\gatewaypro\Middleware\ReconnectMiddleware;
    use afcodehub\gatewaypro\Middleware\Router\SimpleRouter;
    use afcodehub\gatewaypro\Middleware\SanitationMiddleware;
    use afcodehub\gatewaypro\Middleware\SslRedirectMiddleware;
    use afcodehub\gatewaypro\Middleware\TextSearchMiddleware;
    use afcodehub\gatewaypro\Middleware\ValidationMiddleware;
    use afcodehub\gatewaypro\Middleware\WpAuthMiddleware;
    use afcodehub\gatewaypro\Middleware\XmlMiddleware;
    use afcodehub\gatewaypro\Middleware\XsrfMiddleware;
    use afcodehub\gatewaypro\OpenApi\OpenApiService;
    use afcodehub\gatewaypro\Record\RecordService;
    class Api implements RequestHandlerInterface
    {
        private $router;
        public function __construct(Config $config)
        {
            $db = new GenericDB(
                $config->getDriver(),
                $config->getAddress(),
                $config->getPort(),
                $config->getDatabase(),
                $config->getCommand(),
                $config->getTables(),
                $config->getMapping(),
                $config->getUsername(),
                $config->getPassword(),
                $config->getGeometrySrid()
            );
            $prefix = sprintf('gatewaypro-%s-', substr($config->getUID(), 0, 8));
            $cache = CacheFactory::create($config->getCacheType(), $prefix, $config->getCachePath());
            $reflection = new ReflectionService($db, $cache, $config->getCacheTime());
            $responder = new JsonResponder($config->getJsonOptions(), $config->getDebug());
            $router = new SimpleRouter($config->getBasePath(), $responder, $cache, $config->getCacheTime());
            foreach ($config->getMiddlewares() as $middleware) {
                switch ($middleware) {
                    case 'sslRedirect':
                        new SslRedirectMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'cors':
                        new CorsMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'firewall':
                        new FirewallMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'TokenAuth':
                        new TokenAuthMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'apiKeyDbAuth':
                        new ApiKeyDbAuthMiddleware($router, $responder, $config, $middleware, $reflection, $db);
                        break;
                    case 'basicAuth':
                        new BasicAuthMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'jwtAuth':
                        new JwtAuthMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'dbAuth':
                        new DbAuthMiddleware($router, $responder, $config, $middleware, $reflection, $db);
                        break;
                    case 'wpAuth':
                        new WpAuthMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'reconnect':
                        new ReconnectMiddleware($router, $responder, $config, $middleware, $reflection, $db);
                        break;
                    case 'validation':
                        new ValidationMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'ipAddress':
                        new IpAddressMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'sanitation':
                        new SanitationMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'multiTenancy':
                        new MultiTenancyMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'authorization':
                        new AuthorizationMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'xsrf':
                        new XsrfMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'pageLimits':
                        new PageLimitsMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'joinLimits':
                        new JoinLimitsMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'customization':
                        new CustomizationMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'textSearch':
                        new TextSearchMiddleware($router, $responder, $config, $middleware, $reflection);
                        break;
                    case 'xml':
                        new XmlMiddleware($router, $responder, $config, $middleware);
                        break;
                    case 'json':
                        new JsonMiddleware($router, $responder, $config, $middleware);
                        break;
                }
            }
            foreach ($config->getControllers() as $controller) {
                switch ($controller) {
                    case 'records':
                        $records = new RecordService($db, $reflection);
                        new RecordController($router, $responder, $records);
                        break;
                    case 'columns':
                        $definition = new DefinitionService($db, $reflection);
                        new ColumnController($router, $responder, $reflection, $definition);
                        break;
                    case 'cache':
                        new CacheController($router, $responder, $cache);
                        break;
                    case 'openapi':
                        $openApi = new OpenApiService($reflection, $config->getOpenApiBase(), $config->getControllers(), $config->getCustomOpenApiBuilders());
                        new OpenApiController($router, $responder, $openApi);
                        break;
                    case 'geojson':
                        $records = new RecordService($db, $reflection);
                        $geoJson = new GeoJsonService($reflection, $records);
                        new GeoJsonController($router, $responder, $geoJson);
                        break;
                    case 'status':
                        new StatusController($router, $responder, $cache, $db);
                        break;
                }
            }
            foreach ($config->getCustomControllers() as $className) {
                if (class_exists($className)) {
                    new $className($router, $responder, $db, $reflection, $cache);
                }
            }
            $this->router = $router;
        }
        private function parseBody(string $body) /*: ?object*/
        {
            $first = substr(ltrim($body), 0, 1);
            if ($first == '[' || $first == '{') {
                $object = json_decode($body);
                $causeCode = json_last_error();
                if ($causeCode !== JSON_ERROR_NONE) {
                    $object = null;
                }
            } else {
                parse_str($body, $input);
                foreach ($input as $key => $value) {
                    if (substr($key, -9) == '__is_null') {
                        $input[substr($key, 0, -9)] = null;
                        unset($input[$key]);
                    }
                }
                $object = (object) $input;
            }
            return $object;
        }
        private function addParsedBody(ServerRequestInterface $request): ServerRequestInterface
        {
            $parsedBody = $request->getParsedBody();
            if ($parsedBody) {
                $request = $this->applyParsedBodyHack($request);
            } else {
                $body = $request->getBody();
                if ($body->isReadable()) {
                    if ($body->isSeekable()) {
                        $body->rewind();
                    }
                    $contents = $body->getContents();
                    if ($body->isSeekable()) {
                        $body->rewind();
                    }
                    if ($contents) {
                        $parsedBody = $this->parseBody($contents);
                        $request = $request->withParsedBody($parsedBody);
                    }
                }
            }
            return $request;
        }
        private function applyParsedBodyHack(ServerRequestInterface $request): ServerRequestInterface
        {
            $parsedBody = $request->getParsedBody();
            if (is_array($parsedBody)) { // is it really?
                $contents = json_encode($parsedBody);
                $parsedBody = $this->parseBody($contents);
                $request = $request->withParsedBody($parsedBody);
            }
            return $request;
        }
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->router->route($this->addParsedBody($request));
        }
    }
}
namespace afcodehub\gatewaypro {
    use Nyholm\Psr7\Factory\Psr17Factory;
    use Nyholm\Psr7Server\ServerRequestCreator;
    use Psr\Http\Message\ServerRequestInterface;
    class RequestFactory
    {
        public static function fromGlobals(): ServerRequestInterface
        {
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
            $serverRequest = $creator->fromGlobals();
            $stream = $psr17Factory->createStreamFromFile('php://input');
            $serverRequest = $serverRequest->withBody($stream);
            return $serverRequest;
        }
        public static function fromString(string $request): ServerRequestInterface
        {
            $parts = explode("\n\n", trim($request), 2);
            $lines = explode("\n", $parts[0]);
            $first = explode(' ', trim(array_shift($lines)), 2);
            $method = $first[0];
            $body = isset($parts[1]) ? $parts[1] : '';
            $url = isset($first[1]) ? $first[1] : '';
            $psr17Factory = new Psr17Factory();
            $serverRequest = $psr17Factory->createServerRequest($method, $url);
            foreach ($lines as $line) {
                list($key, $value) = explode(':', $line, 2);
                $serverRequest = $serverRequest->withAddedHeader($key, $value);
            }
            if ($body) {
                $stream = $psr17Factory->createStream($body);
                $stream->rewind();
                $serverRequest = $serverRequest->withBody($stream);
            }
            return $serverRequest;
        }
    }
}
namespace afcodehub\gatewaypro {
    use Psr\Http\Message\ServerRequestInterface;
    use afcodehub\gatewaypro\Column\ReflectionService;
    class RequestUtils
    {
        public static function setParams(ServerRequestInterface $request, array $params): ServerRequestInterface
        {
            $query = preg_replace('|%5B[0-9]+%5D=|', '=', http_build_query($params));
            return $request->withUri($request->getUri()->withQuery($query));
        }
        public static function getHeader(ServerRequestInterface $request, string $header): string
        {
            $headers = $request->getHeader($header);
            return isset($headers[0]) ? $headers[0] : '';
        }
        public static function getParams(ServerRequestInterface $request): array
        {
            $params = array();
            $query = $request->getUri()->getQuery();
            //$query = str_replace('][]=', ']=', str_replace('=', '[]=', $query));
            $query = str_replace('%5D%5B%5D=', '%5D=', str_replace('=', '%5B%5D=', $query));
            parse_str($query, $params);
            return $params;
        }
        public static function getPathSegment(ServerRequestInterface $request, int $part): string
        {
            $path = $request->getUri()->getPath();
            $pathSegments = explode('/', rtrim($path, '/'));
            if ($part < 0 || $part >= count($pathSegments)) {
                return '';
            }
            return urldecode($pathSegments[$part]);
        }
        public static function getOperation(ServerRequestInterface $request): string
        {
            $method = $request->getMethod();
            $path = RequestUtils::getPathSegment($request, 1);
            $hasPk = RequestUtils::getPathSegment($request, 3) != '';
            switch ($path) {
                case 'openapi':
                    return 'document';
                case 'columns':
                    return $method == 'get' ? 'reflect' : 'remodel';
                case 'geojson':
                case 'records':
                    switch ($method) {
                        case 'POST':
                            return 'create';
                        case 'GET':
                            return $hasPk ? 'read' : 'list';
                        case 'PUT':
                            return 'update';
                        case 'DELETE':
                            return 'delete';
                        case 'PATCH':
                            return 'increment';
                    }
            }
            return 'unknown';
        }
        private static function getJoinTables(string $tableName, array $parameters): array
        {
            $uniqueTableNames = array();
            $uniqueTableNames[$tableName] = true;
            if (isset($parameters['join'])) {
                foreach ($parameters['join'] as $parameter) {
                    $tableNames = explode(',', trim($parameter));
                    foreach ($tableNames as $tableName) {
                        $uniqueTableNames[$tableName] = true;
                    }
                }
            }
            return array_keys($uniqueTableNames);
        }
        public static function getTableNames(ServerRequestInterface $request, ReflectionService $reflection): array
        {
            $path = RequestUtils::getPathSegment($request, 1);
            $tableName = RequestUtils::getPathSegment($request, 2);
            $allTableNames = $reflection->getTableNames();
            switch ($path) {
                case 'openapi':
                    return $allTableNames;
                case 'columns':
                    return $tableName ? [$tableName] : $allTableNames;
                case 'records':
                    return self::getJoinTables($tableName, RequestUtils::getParams($request));
            }
            return $allTableNames;
        }
        public static function toString(ServerRequestInterface $request): string
        {
            $method = $request->getMethod();
            $uri = $request->getUri()->__toString();
            $headers = $request->getHeaders();
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $str = "$method $uri\n";
            foreach ($headers as $key => $values) {
                foreach ($values as $value) {
                    $str .= "$key: $value\n";
                }
            }
            if ($body !== '') {
                $str .= "\n";
                $str .= "$body\n";
            }
            return $str;
        }
    }
}
namespace afcodehub\gatewaypro {
    use Nyholm\Psr7\Factory\Psr17Factory;
    use Psr\Http\Message\ResponseInterface;
    class ResponseFactory
    {
        const OK = 200;
        const MOVED_PERMANENTLY = 301;
        const FOUND = 302;
        const UNAUTHORIZED = 401;
        const FORBIDDEN = 403;
        const NOT_FOUND = 404;
        const METHOD_NOT_ALLOWED = 405;
        const CONFLICT = 409;
        const UNPROCESSABLE_ENTITY = 422;
        const FAILED_DEPENDENCY = 424;
        const INTERNAL_SERVER_ERROR = 500;
        public static function fromXml(int $status, string $xml): ResponseInterface
        {
            return self::from($status, 'text/xml', $xml);
        }
        public static function fromCsv(int $status, string $csv): ResponseInterface
        {
            return self::from($status, 'text/csv', $csv);
        }
        public static function fromHtml(int $status, string $html): ResponseInterface
        {
            return self::from($status, 'text/html', $html);
        }
        public static function fromObject(int $status, $body, int $jsonOptions): ResponseInterface
        {
            $content = json_encode($body, $jsonOptions);
            return self::from($status, 'application/json', $content);
        }
        public static function from(int $status, string $contentType, string $content): ResponseInterface
        {
            $psr17Factory = new Psr17Factory();
            $response = $psr17Factory->createResponse($status);
            $stream = $psr17Factory->createStream($content);
            $stream->rewind();
            $response = $response->withBody($stream);
            $response = $response->withHeader('Content-Type', $contentType . '; charset=utf-8');
            $response = $response->withHeader('Content-Length', strlen($content));
            return $response;
        }
        public static function fromStatus(int $status): ResponseInterface
        {
            $psr17Factory = new Psr17Factory();
            return $psr17Factory->createResponse($status);
        }
    }
}
namespace afcodehub\gatewaypro {
    use Psr\Http\Message\ResponseInterface;
    class ResponseUtils
    {
        public static function output(ResponseInterface $response)
        {
            $status = $response->getStatusCode();
            $headers = $response->getHeaders();
            $body = $response->getBody()->getContents();
            http_response_code($status);
            foreach ($headers as $key => $values) {
                foreach ($values as $value) {
                    header("$key: $value");
                }
            }
            echo $body;
        }
        public static function addExceptionHeaders(ResponseInterface $response, \Throwable $e): ResponseInterface
        {
            $response = $response->withHeader('X-Exception-Name', get_class($e));
            $response = $response->withHeader('X-Exception-Message', preg_replace('|\n|', ' ', trim($e->getMessage())));
            $response = $response->withHeader('X-Exception-File', $e->getFile() . ':' . $e->getLine());
            return $response;
        }
        public static function toString(ResponseInterface $response): string
        {
            $status = $response->getStatusCode();
            $headers = $response->getHeaders();
            $response->getBody()->rewind();
            $body = $response->getBody()->getContents();
            $str = "$status\n";
            foreach ($headers as $key => $values) {
                foreach ($values as $value) {
                    $str .= "$key: $value\n";
                }
            }
            if ($body !== '') {
                $str .= "\n";
                $str .= "$body\n";
            }
            return $str;
        }
    }
}
namespace afcodehub\gatewaypro {
    use afcodehub\gatewaypro\Api;
    use afcodehub\gatewaypro\Config\Config;
    use afcodehub\gatewaypro\RequestFactory;
    use afcodehub\gatewaypro\ResponseUtils;
    require_once __DIR__ . '/config/config.php';
    $apiConfig = new Config([
        'driver' => 'mysql',
        'address' => DB_HOST,
        'port' => '3306',
        'username' => DB_USER,
        'password' => DB_PASS,
        'database' => DB_NAME,
        'debug' => true,
        'middlewares' => 'cors,authorization,TokenAuth,xsrf,pageLimits,joinLimits,customization,textSearch,json',
    ]);
    $request = RequestFactory::fromGlobals();
    $api = new Api($apiConfig);
    $response = $api->handle($request);
    ResponseUtils::output($response);
}