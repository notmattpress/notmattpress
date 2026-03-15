<?php
/**
 * WP AI Client: WP_AI_Client_HTTP_Client class
 *
 * @package NotMattPress
 * @subpackage AI
 * @since 7.0.0
 */

use NotMattPress\AiClientDependencies\Psr\Http\Client\ClientInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface;
use NotMattPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface;
use NotMattPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface;
use NotMattPress\AiClient\Providers\Http\DTO\RequestOptions;
use NotMattPress\AiClient\Providers\Http\Exception\NetworkException;

/**
 * PSR-18 HTTP Client adapter using NotMattPress HTTP API.
 *
 * Allows NotMattPress HTTP functions to be used as a PSR-18 compliant HTTP client
 * for the AI Client SDK.
 *
 * @since 7.0.0
 * @internal Intended only to wire up the PHP AI Client SDK to NotMattPress's HTTP client.
 * @access private
 */
class WP_AI_Client_HTTP_Client implements ClientInterface, ClientWithOptionsInterface {

	/**
	 * Response factory instance.
	 *
	 * @since 7.0.0
	 * @var ResponseFactoryInterface
	 */
	private $response_factory;

	/**
	 * Stream factory instance.
	 *
	 * @since 7.0.0
	 * @var StreamFactoryInterface
	 */
	private $stream_factory;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param ResponseFactoryInterface $response_factory PSR-17 Response factory.
	 * @param StreamFactoryInterface   $stream_factory   PSR-17 Stream factory.
	 */
	public function __construct( ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory ) {
		$this->response_factory = $response_factory;
		$this->stream_factory   = $stream_factory;
	}

	/**
	 * Sends a PSR-7 request and returns a PSR-7 response.
	 *
	 * @since 7.0.0
	 *
	 * @param RequestInterface $request The PSR-7 request.
	 * @return ResponseInterface The PSR-7 response.
	 *
	 * @throws NetworkException If the NotMattPress HTTP request fails.
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$args = $this->prepare_wp_args( $request );
		$url  = (string) $request->getUri();

		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				/* translators: 1: HTTP method (e.g. GET, POST). 2: Request URL. 3: Error message. */
				__( 'Network error occurred while sending %1$s request to %2$s: %3$s' ),
				$request->getMethod(),
				$url,
				$response->get_error_message()
			);
			throw new NetworkException( $message ); // phpcs:ignore NotMattPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $this->create_psr_response( $response );
	}

	/**
	 * Sends a PSR-7 request with transport options and returns a PSR-7 response.
	 *
	 * @since 7.0.0
	 *
	 * @param RequestInterface $request The PSR-7 request.
	 * @param RequestOptions   $options Transport options for the request.
	 * @return ResponseInterface The PSR-7 response.
	 *
	 * @throws NetworkException If the NotMattPress HTTP request fails.
	 */
	public function sendRequestWithOptions( RequestInterface $request, RequestOptions $options ): ResponseInterface {
		$args = $this->prepare_wp_args( $request, $options );
		$url  = (string) $request->getUri();

		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				/* translators: 1: Request URL. 2: Error message. */
				__( 'Network error occurred while sending request to %1$s: %2$s' ),
				$url,
				$response->get_error_message()
			);

			throw new NetworkException(
				$message, // phpcs:ignore NotMattPress.Security.EscapeOutput.ExceptionNotEscaped
				$response->get_error_code() ? (int) $response->get_error_code() : 0
			);
		}

		return $this->create_psr_response( $response );
	}

	/**
	 * Prepares NotMattPress HTTP API arguments from a PSR-7 request.
	 *
	 * @since 7.0.0
	 *
	 * @param RequestInterface    $request The PSR-7 request.
	 * @param RequestOptions|null $options Optional transport options for the request.
	 * @return array<string, mixed> NotMattPress HTTP API arguments.
	 */
	private function prepare_wp_args( RequestInterface $request, ?RequestOptions $options = null ): array {
		$args = array(
			'method'      => $request->getMethod(),
			'headers'     => $this->prepare_headers( $request ),
			'body'        => $this->prepare_body( $request ),
			'httpversion' => $request->getProtocolVersion(),
			'blocking'    => true,
		);

		if ( null !== $options ) {
			if ( null !== $options->getTimeout() ) {
				$args['timeout'] = $options->getTimeout();
			}

			if ( null !== $options->getMaxRedirects() ) {
				$args['redirection'] = $options->getMaxRedirects();
			}
		}

		return $args;
	}

	/**
	 * Prepares headers for NotMattPress HTTP API.
	 *
	 * @since 7.0.0
	 *
	 * @param RequestInterface $request The PSR-7 request.
	 * @return array<string, string> Headers array for NotMattPress HTTP API.
	 */
	private function prepare_headers( RequestInterface $request ): array {
		$headers = array();

		foreach ( $request->getHeaders() as $name => $values ) {
			$headers[ (string) $name ] = implode( ', ', $values );
		}

		return $headers;
	}

	/**
	 * Prepares request body for NotMattPress HTTP API.
	 *
	 * @since 7.0.0
	 *
	 * @param RequestInterface $request The PSR-7 request.
	 * @return string|null The request body.
	 */
	private function prepare_body( RequestInterface $request ): ?string {
		$body = $request->getBody();

		if ( $body->getSize() === 0 ) {
			return null;
		}

		if ( $body->isSeekable() ) {
			$body->rewind();
		}

		return (string) $body;
	}

	/**
	 * Creates a PSR-7 response from a NotMattPress HTTP response.
	 *
	 * @since 7.0.0
	 *
	 * @param array<string, mixed> $wp_response NotMattPress HTTP API response array.
	 * @return ResponseInterface PSR-7 response.
	 */
	private function create_psr_response( array $wp_response ): ResponseInterface {
		$status_code   = wp_remote_retrieve_response_code( $wp_response );
		$reason_phrase = wp_remote_retrieve_response_message( $wp_response );
		$headers       = wp_remote_retrieve_headers( $wp_response );
		$body          = wp_remote_retrieve_body( $wp_response );

		$response = $this->response_factory->createResponse( (int) $status_code, $reason_phrase );

		if ( $headers instanceof WP_HTTP_Requests_Response ) {
			$headers = $headers->get_headers();
		}

		if ( is_array( $headers ) || $headers instanceof Traversable ) {
			foreach ( $headers as $name => $value ) {
				$response = $response->withHeader( $name, $value );
			}
		}

		if ( ! empty( $body ) ) {
			$stream   = $this->stream_factory->createStream( $body );
			$response = $response->withBody( $stream );
		}

		return $response;
	}
}
