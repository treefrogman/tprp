<?php
/**
 * Copyright 2016 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Speech;

use Google\Cloud\Core\ClientTrait;
use Google\Cloud\Speech\Connection\ConnectionInterface;
use Google\Cloud\Speech\Connection\Rest;
use Google\Cloud\Storage\StorageObject;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Google Cloud Speech enables easy integration of Google speech recognition
 * technologies into developer applications. Send audio and receive a text
 * transcription from the Cloud Speech API service. Find more information at the
 * [Google Cloud Speech docs](https://cloud.google.com/speech/docs/).
 *
 * Example:
 * ```
 * use Google\Cloud\Speech\SpeechClient;
 *
 * $speech = new SpeechClient([
 *     'languageCode' => 'en-US'
 * ]);
 * ```
 */
class SpeechClient
{
    use ClientTrait;

    const VERSION = '0.2.0';

    const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var string
     */
    private $languageCode;

    /**
     * Create a Speech client.
     *
     * @param array $config [optional] {
     *     Configuration Options.
     *
     *     @type string $projectId The project ID from the Google Developer's
     *           Console.
     *     @type CacheItemPoolInterface $authCache A cache for storing access
     *           tokens. **Defaults to** a simple in memory implementation.
     *     @type array $authCacheOptions Cache configuration options.
     *     @type callable $authHttpHandler A handler used to deliver Psr7
     *           requests specifically for authentication.
     *     @type callable $httpHandler A handler used to deliver Psr7 requests.
     *           Only valid for requests sent over REST.
     *     @type array $keyFile The contents of the service account credentials
     *           .json file retrieved from the Google Developer's Console.
     *           Ex: `json_decode(file_get_contents($path), true)`.
     *     @type string $keyFilePath The full path to your service account
     *           credentials .json file retrieved from the Google Developers
     *           Console.
     *     @type int $retries Number of retries for a failed request.
     *           **Defaults to** `3`.
     *     @type array $scopes Scopes to be used for the request.
     *     @type string $languageCode Required. The language of the content to
     *           be recognized. Only BCP-47 (e.g., `"en-US"`, `"es-ES"`)
     *           language codes are accepted. See
     *           [Language Support](https://cloud.google.com/speech/docs/languages)
     *           for a list of the currently supported language codes.
     * }
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['languageCode'])) {
            throw new \InvalidArgumentException('A valid BCP-47 language code is required.');
        }

        if (!isset($config['scopes'])) {
            $config['scopes'] = [self::SCOPE];
        }

        $this->languageCode = $config['languageCode'];
        unset($config['languageCode']);
        $this->connection = new Rest($this->configureAuthentication($config));
    }

    /**
     * Runs a recognize request and returns the results immediately. Ideal when
     * working with audio up to approximately one minute in length.
     *
     * The Google Cloud Client Library will attempt to infer the sample rate
     * and encoding used by the provided audio file for you. This feature is
     * recommended only if you are unsure of what the values may be and is
     * currently limited to .flac, .amr, and .awb file types.
     *
     * Example:
     * ```
     * $results = $speech->recognize(
     *     fopen(__DIR__  . '/audio.flac', 'r')
     * );
     *
     * foreach ($results as $result) {
     *     echo $result['transcript'];
     * }
     * ```
     *
     * ```
     * // Run with speech contexts, sample rate, and encoding provided
     * $results = $speech->recognize(
     *     fopen(__DIR__  . '/audio.flac', 'r'), [
     *     'encoding' => 'FLAC',
     *     'sampleRateHertz' => 16000,
     *     'speechContexts' => [
     *         [
     *             'phrases' => [
     *                 'The Google Cloud Platform',
     *                 'Speech API'
     *             ]
     *         ]
     *     ]
     * ]);
     *
     * foreach ($results as $result) {
     *     echo $result['transcript'];
     * }
     * ```
     *
     * @codingStandardsIgnoreStart
     * @see https://cloud.google.com/speech/reference/rest/v1/speech/recognize#SpeechRecognitionAlternative SpeechRecognitionAlternative
     * @see https://cloud.google.com/speech/reference/rest/v1/speech/recognize Recognize API documentation
     * @see https://cloud.google.com/speech/reference/rest/v1/RecognitionConfig#AudioEncoding AudioEncoding types
     * @see https://cloud.google.com/speech/docs/best-practices Speech API best practices
     * @codingStandardsIgnoreEnd
     *
    * @param resource|string|StorageObject $audio The audio to recognize. May
     *        be a resource, string of bytes, a URI pointing to a
     *        Google Cloud Storage object in the format of
     *        `gs://{bucket-name}/{object-name}` or a
     *        {@see Google\Cloud\Storage\StorageObject}.
     * @param array $options [optional] {
     *     Configuration options.
     *
     *     @type bool $detectGcsUri When providing $audio as a string, this flag
     *           determines whether or not to attempt to detect if the string
     *           represents a Google Cloud Storage URI in the format of
     *           `gs://{bucket-name}/{object-name}`. **Defaults to** `true`.
     *     @type string $languageCode The language of the content. BCP-47
     *           (e.g., `"en-US"`, `"es-ES"`) language codes are accepted. See
     *           [Language Support](https://cloud.google.com/speech/docs/languages)
     *           for a list of the currently supported language codes.
     *           **Defaults to** the value set on the client.
     *     @type int $sampleRateHertz Sample rate in Hertz of the provided
     *           audio. Valid values are: 8000-48000. 16000 is optimal. For best
     *           results, set the sampling rate of the audio source to 16000 Hz.
     *           If that's not possible, use the native sample rate of the audio
     *           source (instead of re-sampling). **Defaults to** `8000` with
     *           .amr files and `16000` with .awb files. For .flac files the
     *           Speech API will make a best effort to read the sample rate from
     *           the file's headers.
     *     @type string $encoding Encoding of the provided audio. May be one of
     *           `"LINEAR16"`, `"FLAC"`, `"MULAW"`, `"AMR"`, `"AMR_WB"`.
     *           **Defaults to** `"FLAC"` with .flac files, `"AMR"` with .amr
     *           files and `"AMR_WB"` with .awb files.
     *     @type int $maxAlternatives Maximum number of alternatives to be
     *           returned. Valid values are 1-30. **Defaults to** `1`.
     *     @type bool $profanityFilter If set to `true`, the server will attempt
     *           to filter out profanities, replacing all but the initial
     *           character in each filtered word with asterisks, e.g. \"f***\".
     *           **Defaults to** `false`.
     *     @type array $speechContexts A list of arrays where each element must
     *           contain a key `phrases`. Each key `phrases` should contain an
     *           array of strings which provide "hints" to the speech recognizer
     *           to favor specific words and phrases in the results. Please see
     *           [SpeechContext](https://cloud.google.com/speech/reference/rest/v1/RecognitionConfig#SpeechContext)
     *           for more information.
     * }
     * @return array The transcribed results. Each element of the array contains
     *         a `transcript` key which holds the transcribed text. Optionally
     *         a `confidence` key holding the confidence estimate ranging from
     *         0.0 to 1.0 may be present. `confidence` is typically provided
     *         only for the top hypothesis.
     * @throws \InvalidArgumentException
     */
    public function recognize($audio, array $options = [])
    {
        $response = $this->connection->recognize(
            $this->formatRequest($audio, $options)
        );

        return isset($response['results']) ? $response['results'][0]['alternatives'] : [];
    }

    /**
     * Runs a recognize request as an operation. Ideal when working with audio
     * longer than approximately one minute. Requires polling of the returned
     * operation in order to fetch results.
     *
     * The Google Cloud Client Library will attempt to infer the sample rate
     * and encoding used by the provided audio file for you. This feature is
     * recommended only if you are unsure of what the values may be and is
     * currently limited to .flac, .amr, and .awb file types.
     *
     * For longer audio, up to approximately 80 minutes, you must use Google
     * Cloud Storage objects as input. In addition to this restriction, only
     * LINEAR16 audio encoding can be used for long audio inputs.
     *
     * Example:
     * ```
     * $operation = $speech->beginRecognizeOperation(
     *     fopen(__DIR__  . '/audio.flac', 'r')
     * );
     *
     * $isComplete = $operation->isComplete();
     *
     * while (!$isComplete) {
     *     sleep(1); // let's wait for a moment...
     *     $operation->reload();
     *     $isComplete = $operation->isComplete();
     * }
     *
     * print_r($operation->results());
     * ```
     *
     * ```
     * // Run with speech contexts, sample rate, and encoding provided
     * $operation = $speech->beginRecognizeOperation(
     *     fopen(__DIR__  . '/audio.flac', 'r'), [
     *     'encoding' => 'FLAC',
     *     'sampleRateHertz' => 16000,
     *     'speechContexts' => [
     *         [
     *             'phrases' => [
     *                 'The Google Cloud Platform',
     *                 'Speech API'
     *             ]
     *         ]
     *     ]
     * ]);
     *
     * $isComplete = $operation->isComplete();
     *
     * while (!$isComplete) {
     *     sleep(1); // let's wait for a moment...
     *     $operation->reload();
     *     $isComplete = $operation->isComplete();
     * }
     *
     * print_r($operation->results());
     * ```
     *
     * @codingStandardsIgnoreStart
     * @see https://cloud.google.com/speech/reference/rest/v1/operations Operations
     * @see https://cloud.google.com/speech/reference/rest/v1/speech/longrunningrecognize LongRunningRecognize API documentation
     * @see https://cloud.google.com/speech/reference/rest/v1/RecognitionConfig#AudioEncoding AudioEncoding types
     * @see https://cloud.google.com/speech/docs/best-practices Speech API best practices
     * @codingStandardsIgnoreEnd
     *
     * @param resource|string|StorageObject $audio The audio to recognize. May
     *        be a resource, string of bytes, a URI pointing to a
     *        Google Cloud Storage object in the format of
     *        `gs://{bucket-name}/{object-name}` or a
     *        {@see Google\Cloud\Storage\StorageObject}.
     * @param array $options [optional] {
     *     Configuration options.
     *
     *     @type bool $detectGcsUri When providing $audio as a string, this flag
     *           determines whether or not to attempt to detect if the string
     *           represents a Google Cloud Storage URI in the format of
     *           `gs://{bucket-name}/{object-name}`. **Defaults to** `true`.
     *     @type string $languageCode The language of the content. BCP-47
     *           (e.g., `"en-US"`, `"es-ES"`) language codes are accepted. See
     *           [Language Support](https://cloud.google.com/speech/docs/languages)
     *           for a list of the currently supported language codes.
     *           **Defaults to** the value set on the client.
     *     @type int $sampleRateHertz Sample rate in Hertz of the provided
     *           audio. Valid values are: 8000-48000. 16000 is optimal. For best
     *           results, set the sampling rate of the audio source to 16000 Hz.
     *           If that's not possible, use the native sample rate of the audio
     *           source (instead of re-sampling). **Defaults to** `8000` with
     *           .amr files and `16000` with .awb files. For .flac files the
     *           Speech API will make a best effort to read the sample rate from
     *           the file's headers.
     *     @type string $encoding Encoding of the provided audio. May be one of
     *           `"LINEAR16"`, `"FLAC"`, `"MULAW"`, `"AMR"`, `"AMR_WB"`.
     *           **Defaults to** `"FLAC"` with .flac files, `"AMR"` with .amr
     *           files and `"AMR_WB"` with .awb files.
     *     @type int $maxAlternatives Maximum number of alternatives to be
     *           returned. Valid values are 1-30. **Defaults to** `1`.
     *     @type bool $profanityFilter If set to `true`, the server will attempt
     *           to filter out profanities, replacing all but the initial
     *           character in each filtered word with asterisks, e.g. \"f***\".
     *           **Defaults to** `false`.
     *     @type array $speechContexts A list of arrays where each element must
     *           contain a key `phrases`. Each key `phrases` should contain an
     *           array of strings which provide "hints" to the speech recognizer
     *           to favor specific words and phrases in the results. Please see
     *           [SpeechContext](https://cloud.google.com/speech/reference/rest/v1/RecognitionConfig#SpeechContext)
     *           for more information.
     * }
     * @return Operation
     * @throws \InvalidArgumentException
     */
    public function beginRecognizeOperation($audio, array $options = [])
    {
        $response = $this->connection->longRunningRecognize(
            $this->formatRequest($audio, $options)
        );

        return new Operation(
            $this->connection,
            $response['name'],
            $response
        );
    }

    /**
     * Lazily instantiates an operation. There are no network requests made at
     * this point. To see the operations that can be performed on an operation
     * please see {@see Google\Cloud\Speech\Operation}.
     *
     * Example:
     * ```
     * // Access an existing operation by its server generated name.
     * $operation = $speech->operation($operationName);
     * ```
     *
     * @param string $name The name of the operation to request.
     * @return Operation
     */
    public function operation($name)
    {
        return new Operation($this->connection, $name);
    }

    /**
     * Formats the request for the API.
     *
     * @param resource|string|StorageObject $audio
     * @param array $options
     * @return array
     * @throws \InvalidArgumentException
     */
    private function formatRequest($audio, array $options)
    {
        $fileFormat = null;
        $options += ['detectGcsUri' => true];
        $recognizeOptions = [
            'encoding',
            'sampleRateHertz',
            'languageCode',
            'maxAlternatives',
            'profanityFilter',
            'speechContexts'
        ];

        if ($audio instanceof StorageObject) {
            $options['audio']['uri'] = $audio->gcsUri();
            $fileFormat = pathinfo($options['audio']['uri'], PATHINFO_EXTENSION);
        } elseif (is_resource($audio)) {
            $options['audio']['content'] = base64_encode(stream_get_contents($audio));
            $fileFormat = pathinfo(stream_get_meta_data($audio)['uri'], PATHINFO_EXTENSION);
        } elseif ($options['detectGcsUri'] && substr($audio, 0, 5) === 'gs://') {
            $options['audio']['uri'] = $audio;
            $fileFormat = pathinfo($options['audio']['uri'], PATHINFO_EXTENSION);
        } else {
            $options['audio']['content'] = base64_encode($audio);
        }

        unset($options['detectGcsUri']);

        $options['languageCode'] = isset($options['languageCode'])
            ? $options['languageCode']
            : $this->languageCode;

        $options['encoding'] = isset($options['encoding'])
            ? $options['encoding']
            : $this->determineEncoding($fileFormat);

        $options['sampleRateHertz'] = isset($options['sampleRateHertz'])
            ? $options['sampleRateHertz']
            : $this->determineSampleRate($options['encoding']);

        if (!$options['sampleRateHertz']) {
            unset($options['sampleRateHertz']);
        }

        foreach ($options as $option => $value) {
            if (in_array($option, $recognizeOptions)) {
                $options['config'][$option] = $value;
                unset($options[$option]);
            }
        }

        return $options;
    }

    /**
     * Attempts to determine the encoding based on the file format.
     *
     * @param string $fileFormat
     * @return string
     * @throws \InvalidArgumentException
     */
    private function determineEncoding($fileFormat)
    {
        switch ($fileFormat) {
            case 'flac':
                return 'FLAC';
            case 'amr':
                return 'AMR';
            case 'awb':
                return 'AMR_WB';
            default:
                throw new \InvalidArgumentException(
                    'Unable to determine encoding. Please provide the value manually.'
                );
        }
    }

    /**
     * Attempts to determine the sample rate based on the encoding.
     *
     * @param string $encoding
     * @return int|null
     * @throws \InvalidArgumentException
     */
    private function determineSampleRate($encoding)
    {
        switch ($encoding) {
            case 'AMR':
                return 8000;
            case 'AMR_WB':
                return 16000;
            case 'FLAC':
                return null;
            default:
                throw new \InvalidArgumentException(
                    'Unable to determine sample rate. Please provide the value manually.'
                );
        }
    }
}
