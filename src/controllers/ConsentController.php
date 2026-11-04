<?php

namespace vaersaagod\consentmate\controllers;

use Craft;
use craft\web\Controller;
use vaersaagod\consentmate\ConsentMate;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\TooManyRequestsHttpException;

/**
 * Saves a visitor's consent: logs it, and re-sets the consent cookie from the
 * server
 *
 * Called by the runtime with fetch(), from pages that may be statically
 * cached. So there's no CSRF token (that would need a session or a token
 * request); cross-site requests are refused by requiring a JSON body and a
 * same-origin request instead. The action never touches the session.
 *
 * @author    Værsågod
 * @package   ConsentMate
 * @since     1.0.0
 */
class ConsentController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    // Protected Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = ['save'];

    // Public Methods
    // =========================================================================

    /**
     * Saves a consent.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws TooManyRequestsHttpException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     *
     * @author    Værsågod
     * @since     1.0.0
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $plugin = ConsentMate::getInstance();
        $site = Craft::$app->getSites()->getCurrentSite();

        if (!$plugin->getFrontend()->isEnabled($site)) {
            throw new BadRequestHttpException('ConsentMate isn\'t enabled for this site.');
        }

        // A cross-site form can't send a JSON body without a CORS preflight,
        // which is never answered
        if (!str_starts_with(strtolower((string)$this->request->getContentType()), 'application/json')) {
            throw new BadRequestHttpException('Expected a JSON request.');
        }

        if (!$this->_isSameOrigin()) {
            throw new ForbiddenHttpException('Cross-origin requests aren\'t allowed.');
        }

        $this->_rateLimit();

        $payload = $this->request->getBodyParams();

        try {
            $consent = $plugin->getConsents()->save($payload, $site);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        }

        // Check the cookies the browser sent for undeclared ones, and ones set
        // without consent. A failing report never fails the save.
        try {
            $plugin->getCookieReports()->record(
                $site,
                array_keys($_COOKIE),
                $payload['previous'] ?? null,
                \is_string($payload['path'] ?? null) ? (string)parse_url($payload['path'], PHP_URL_PATH) : null
            );
        } catch (\Throwable $e) {
            Craft::warning('Couldn\'t record the cookie report: ' . $e->getMessage(), __METHOD__);
        }

        $this->response->setNoCacheHeaders();
        $this->response->getHeaders()->add('Set-Cookie', $plugin->getConsents()->getCookieHeader($consent));

        return $this->asJson([
            'success' => true,
            'revision' => $consent['revision'],
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether the request comes from the same origin.
     *
     * @return bool
     */
    private function _isSameOrigin(): bool
    {
        $headers = $this->request->getHeaders();
        $fetchSite = $headers->get('Sec-Fetch-Site');

        if (\is_string($fetchSite) && $fetchSite !== '') {
            return $fetchSite === 'same-origin';
        }

        // Browsers without Fetch Metadata still send Origin on POST requests
        $origin = $headers->get('Origin');

        if (!\is_string($origin) || $origin === '') {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);

        return $originHost === $this->request->getHostName()
            && ($originPort === null || (int)$originPort === (int)$this->request->getPort());
    }

    /**
     * Limits how many consents a visitor (by IP) can save per minute.
     *
     * @return void
     * @throws TooManyRequestsHttpException
     */
    private function _rateLimit(): void
    {
        $limit = ConsentMate::getInstance()->getSettings()->rateLimit;
        $cache = Craft::$app->getCache();
        $key = 'consentmate:rate:' . sha1($this->request->getUserIP() . ':' . floor(time() / 60));
        $count = (int)$cache->get($key);

        if ($count >= $limit) {
            $this->response->getHeaders()->set('Retry-After', '60');

            throw new TooManyRequestsHttpException('Too many requests.');
        }

        $cache->set($key, $count + 1, 70);
    }
}
