<?php
/**
 * (C) Copyright 2016 Nuxeo SA (http://nuxeo.com/) and contributors.
 *
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the GNU Lesser General Public License
 * (LGPL) version 2.1 which accompanies this distribution, and is available at
 * http://www.gnu.org/licenses/lgpl-2.1.html
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * Contributors:
 *     Pierre-Gildas MILLON <pgmillon@nuxeo.com>
 */

namespace Nuxeo\Client\Api\Objects;


use Guzzle\Http\Url;
use JMS\Serializer\Annotation as Serializer;
use Nuxeo\Client\Api\Constants;
use Nuxeo\Client\Api\NuxeoClient;
use Nuxeo\Client\Internals\Spi\ClassCastException;
use Nuxeo\Client\Internals\Spi\NoSuchOperationException;
use Nuxeo\Client\Internals\Spi\NuxeoClientException;

class Operation extends NuxeoEntity {

  /**
   * @var string
   */
  protected $operationId;

  /**
   * @var Url
   * @Serializer\Exclude()
   */
  protected $apiUrl;

  /**
   * @var OperationBody
   */
  private $body;

  /**
   * Operation constructor.
   * @param NuxeoClient $nuxeoClient
   * @param Url $apiUrl
   * @param string $operationId
   */
  public function __construct($nuxeoClient, $apiUrl, $operationId = null) {
    parent::__construct(Constants::ENTITY_TYPE_OPERATION, $nuxeoClient);

    $this->operationId = $operationId;
    $this->apiUrl = $apiUrl;
    $this->body = new OperationBody();
  }

  /**
   * Adds an operation param.
   * @param string $name
   * @param string $value
   * @return Operation
   */
  public function param($name, $value) {
    $this->body->addParameter($name, $value);
    return $this;
  }

  /**
   * Adds operation params
   * @param array $params
   * @return Operation
   */
  public function params($params) {
    $this->body->addParameters($params);
    return $this;
  }

  /**
   * Sets operation params
   * @param $params
   * @return Operation
   */
  public function parameters($params) {
    $this->body->setParameters($params);
    return $this;
  }

  /**
   * @param mixed $input
   * @return Operation
   */
  public function input($input) {
    $this->body->setInput($input);
    return $this;
  }

  /**
   * @param string $clazz
   * @param string $operationId
   * @return mixed
   * @throws NuxeoClientException
   * @throws NoSuchOperationException
   * @throws ClassCastException
   */
  public function execute($clazz, $operationId = null) {
    $response = $this->_doExecute($operationId);
    return $this->computeResponse($response, $clazz);
  }

  /**
   * @param string $operationId
   * @return Url
   */
  protected function computeRequestUrl($operationId) {
    return $this->apiUrl->addPath($operationId);
  }

  /**
   * @param $operationId
   * @return \Guzzle\Http\Message\Response
   * @throws NuxeoClientException
   * @throws NoSuchOperationException
   * @throws ClassCastException
   */
  protected function _doExecute($operationId) {
    $operationId = null === $operationId ? $this->operationId : $operationId;
    $input = $this->body->getInput();

    if(null === $operationId) {
      throw new NoSuchOperationException($operationId);
    }

    if($input instanceof Blob) {
      $input = new Blobs(array($input));
    }

    if($input instanceof Blobs) {
      $blobs = array();
      foreach($input->getBlobs() as $blob) {
        $blobs[] = $blob->getFile()->getPathname();
      }
      $this->nuxeoClient->voidOperation(true);

      $response = $this->nuxeoClient->post(
        $this->computeRequestUrl($operationId),
        $this->nuxeoClient->getConverter()->write($this->body),
        $blobs);
    } else {
      $response = $this->nuxeoClient->post(
        $this->computeRequestUrl($operationId),
        $this->nuxeoClient->getConverter()->write($this->body));
    }
    return $response;
  }

}