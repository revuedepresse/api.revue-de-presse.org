<?php

namespace WeavingTheWeb\Bundle\TwitterBundle\Controller;

use Doctrine\DBAL\Exception\ConnectionException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Extra;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
    Symfony\Component\HttpFoundation\JsonResponse,
    Symfony\Component\HttpFoundation\Request;

use WeavingTheWeb\Bundle\ApiBundle\Entity\Status;

/**
 * @package WeavingTheWeb\Bundle\TwitterBundle\Controller
 */
class TweetController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     *
     * @Extra\Route("/tweet/latest", name="weaving_the_web_twitter_tweet_latest")
     *
     * @Extra\Method({"GET", "OPTIONS"})
     *
     * @Extra\Cache(public=true)
     */
    public function latestAction(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->getCorsOptionsResponse();
        }

        try {
            /** @var \WeavingTheWeb\Bundle\ApiBundle\Repository\StatusRepository $userStreamRepository */
            $userStreamRepository = $this->get('weaving_the_web_twitter.repository.read.status');
            // Look for statuses collected by any given access token
            // (there is no restriction at this point of the implementation)
            $userStreamRepository->setOauthTokens([]);

            $lastId = $request->get('lastId', null);
            $aggregateName = $request->attributes->get('aggregate_name', null);

            $rawSql = false;

            if (!is_null($aggregateName)) {
                $aggregateName = str_replace('___', ' ', $aggregateName);
                $aggregateName = str_replace('__', ' :: ', $aggregateName);
                $aggregateName = str_replace('_', ' _ ', $aggregateName);
                $rawSql = true;
            }

            $statuses = $userStreamRepository->findLatest($lastId, $aggregateName, $rawSql);
            $statusCode = 200;

            $statuses = array_map(
                function ($status) {
                    $defaultStatus =  [
                        'status_id' => $status['status_id'],
                        'avatar_url' => 'N/A',
                        'text' => $status['text'],
                        'url' => 'https://twitter.com/'.$status['screen_name'].'/status/'.$status['status_id'],
                        'retweet_count' => 'N/A',
                        'favorite_count' => 'N/A',
                        'username' => $status['screen_name'],
                        'published_at' => 'N/A',
                    ];

                    if (!array_key_exists('original_document', $status)) {
                        return $defaultStatus;
                    }

                    $decodedDocument = json_decode($status['original_document'], $asAssociativeArray = true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $defaultStatus;
                    }

                    $statusUpdatedFromDecodedDocument = $defaultStatus;

                    if (array_key_exists('avatar_url', $decodedDocument)) {
                        $statusUpdatedFromDecodedDocument['avatar_url'] = $decodedDocument['avatar_url'];
                    }

                    if (array_key_exists('user', $decodedDocument) &&
                        array_key_exists('profile_image_url_https', $decodedDocument['user'])) {
                        $statusUpdatedFromDecodedDocument['avatar_url'] = $decodedDocument['user']['profile_image_url_https'];
                    }

                    if (array_key_exists('retweet_count', $decodedDocument)) {
                        $statusUpdatedFromDecodedDocument['retweet_count'] = $decodedDocument['retweet_count'];
                    }

                    if (array_key_exists('favorite_count', $decodedDocument)) {
                        $statusUpdatedFromDecodedDocument['favorite_count'] = $decodedDocument['favorite_count'];
                    }

                    if (array_key_exists('created_at', $decodedDocument)) {
                        $statusUpdatedFromDecodedDocument['published_at'] = $decodedDocument['created_at'];
                    }


                    return $statusUpdatedFromDecodedDocument;

                },
                $statuses
            );

            $encodedStatuses = json_encode($statuses);
            $response = new JsonResponse(
                $statuses,
                $statusCode,
                $this->getAccessControlOriginHeaders()
            );

            $contentLength = strlen($encodedStatuses);
            $response->headers->add([
                'Content-Length' => $contentLength,
                'x-decompressed-content-length' => $contentLength,
                // @see https://stackoverflow.com/a/37931084/282073
                'Access-Control-Expose-Headers' => 'Content-Length, x-decompressed-content-length'
            ]);

            $response->setCache([
                'public' => true,
                'max_age' =>  3600,
                's_maxage' =>  3600,
                'last_modified' => new \DateTime(
                    // last hour
                    (new \DateTime(
                        'now',
                        new \DateTimeZone('UTC'))
                    )->modify('-1 hour')->format('Y-m-d H:0'),
                    new \DateTimeZone('UTC')
                )
            ]);

            return $response;
        } catch (\PDOException $exception) {
            return $this->getExceptionResponse(
                $exception,
                $this->get('translator')->trans('twitter.error.database_connection', [], 'messages')
            );
        } catch (ConnectionException $exception) {
            $this->get('logger')->critical('Could not connect to the database');
        } catch (\Exception $exception) {
            return $this->getExceptionResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     *
     * @Extra\Route(
     *     "/tweet/latest/{aggregate_name}",
     *     name="weaving_the_web_twitter_tweet_latest_for_aggregate",
     *     requirements={"aggregate_name"="\S+"}
     * )
     *
     * @Extra\Method({"GET", "OPTIONS"})
     *
     * @Extra\Cache(public=true)
     */
    public function latestStatusesForAggregates(Request $request)
    {
        return $this->latestAction($request);
    }

    /**
     * @param \Exception $exception
     * @param null $message
     * @return JsonResponse
     */
    protected function getExceptionResponse(\Exception $exception, $message = null)
    {
        if (is_null($message)) {
            $data = ['error' => $exception->getMessage()];
        } else {
            $data = ['error' => $message];
        }

        $this->get('logger')->critical($data['error']);

        $statusCode = 500;

        return new JsonResponse($data, $statusCode, $this->getAccessControlOriginHeaders());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     *
     * @Extra\Route("/bookmarks", name="weaving_the_web_twitter_tweet_sync_bookmarks")
     * @Extra\Method({"POST", "OPTIONS"})
     */
    public function syncBookmarksAction(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->getCorsOptionsResponse();
        } else {
            try {
                $oauthTokens = $this->parseOAuthTokens($request);

                /** @var \WeavingTheWeb\Bundle\ApiBundle\Repository\StatusRepository $userStreamRepository */
                $userStreamRepository = $this->get('weaving_the_web_twitter.repository.read.status');
                $userStreamRepository->setOauthTokens($oauthTokens);

                $statusIds = $request->get('statusIds', array());
                $statuses = $userStreamRepository->findBookmarks($statusIds);

                // TODO Mark statuses as starred before returning them

                $statusCode = 200;

                return new JsonResponse($statuses, $statusCode, $this->getAccessControlOriginHeaders());
            } catch (\Exception $exception) {
                return $this->getExceptionResponse($exception);
            }
        }
    }

    /**
     * @return array
     */
    protected function getAccessControlOriginHeaders()
    {
        if ($this->get('service_container')->getParameter('kernel.environment') === 'prod') {
            $allowedOrigin = $this->get('service_container')->getParameter('allowed.origin');

            return ['Access-Control-Allow-Origin' => $allowedOrigin];
        }

        return ['Access-Control-Allow-Origin' => '*'];
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    protected function parseOAuthTokens(Request $request)
    {
        $userManager = $this->get('user_manager');
        $username = $request->get('username', null);

        if (is_null($username)) {
            $oauthToken = $request->get(
                'token',
                null,
                $this->container->getParameter('weaving_the_web_twitter.oauth_token.default')
            );

            if ($oauthToken !== null) {
                $oauthTokens = [$oauthToken];
            } else {
                throw new \Exception($this->get('translator')->trans('twitter.error.invalid_oauth_token', [], 'messages'));
            }
        } else {
            /** @var \WTW\UserBundle\Entity\User $user */
            $user = $userManager->findOneBy(['twitter_username' => $username]);

            if (is_null($user)) {
                throw new \Exception(sprintf(
                    'No user can be found for username "%s"',
                    $username
                ));
            }

            $tokens = $user->getTokens()->toArray();

            $oauthTokens = [];
            /** @var \WeavingTheWeb\Bundle\ApiBundle\Entity\Token $token */
            foreach ($tokens as $token) {
                $oauthToken = $token->getOauthToken();
                $oauthTokens[] = $token->getOauthToken();
                if (strlen(trim($oauthToken)) === 0) {
                    throw new \Exception(sprintf(
                        'Invalid token for username "%s"',
                        $username
                    ));
                }
            }
        }

        return $oauthTokens;
    }

    /**
     * @return JsonResponse
     */
    protected function getCorsOptionsResponse()
    {
        $allowedHeaders = implode(
            ', ',
            [
                'Keep-Alive',
                'User-Agent',
                'X-Requested-With',
                'If-Modified-Since',
                'Cache-Control',
                'Content-Type',
                'x-auth-token',
                'x-decompressed-content-length'
            ]
        );

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => $allowedHeaders,
        ];
        if ($this->get('service_container')->getParameter('kernel.environment') === 'prod') {
            $allowedOrigin = $this->get('service_container')->getParameter('allowed.origin');
            $headers = [
                'Access-Control-Allow-Origin' => $allowedOrigin,
                'Access-Control-Allow-Headers' => $allowedHeaders,
            ];
        }

        return new JsonResponse(
            [],
            200,
            $headers
        );
    }

    /**
     * @Extra\Route("/tweet/star/{statusId}", name="weaving_the_web_twitter_tweet_star")
     * @Extra\Method({"POST", "OPTIONS"})
     * @Extra\ParamConverter(
     *      "userStream",
     *      class="WeavingTheWebApiBundle:UserStream",
     *      options={"entity_manager"="write"}
     * )
     *
     * @param Status $userStream
     * @return JsonResponse
     */
    public function starAction(Status $userStream)
    {
        return $this->toggleStarringStatus($userStream, $starring = true);
    }

    /**
     * @Extra\Route("/tweet/unstar/{statusId}", name="weaving_the_web_twitter_tweet_unstar")
     * @Extra\Method({"POST", "OPTIONS"})
     * @Extra\ParamConverter(
     *      "userStream",
     *      class="WeavingTheWebApiBundle:UserStream",
     *      options={"entity_manager"="write"}
     * )
     *
     * @param Status $userStream
     * @return JsonResponse
     */
    public function unstarAction(Status $userStream)
    {
        return $this->toggleStarringStatus($userStream, $starring = false);
    }

    /**
     * @param Status $userStream
     * @param bool   $starred
     * @return JsonResponse
     */
    protected function toggleStarringStatus(Status $userStream, $starred = false)
    {
        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $request = $requestStack->getMasterRequest();

        if ($request->isMethod('POST')) {
            $userStream->setStarred($starred);

            $clonedUserStream = clone $userStream;
            $clonedUserStream->setUpdatedAt(new \DateTime());

            $entityManager = $this->getDoctrine()->getManager('write');

            $entityManager->remove($userStream);
            $entityManager->flush();

            $entityManager->persist($clonedUserStream);
            $entityManager->flush();

            return new JsonResponse([
                'status' => $userStream->getStatusId()
            ], 200, ['Access-Control-Allow-Origin' => '*']);
        } else {
            return $this->getCorsOptionsResponse();
        }
    }
}
