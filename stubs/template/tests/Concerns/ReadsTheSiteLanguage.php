<?php

namespace Tests\Concerns;

use NickDeKruijk\Leap\Leap;

/**
 * Say which language the test is reading the site in.
 *
 * The bare root of a multilingual site offers a first-time visitor the language their
 * browser asks for (PageController::preferredLocaleRedirect). Symfony's Request::create,
 * which every test request goes through, supplies "en-us,en;q=0.5" when nothing says
 * otherwise -- so in a test the browser always claims to want English, and a Dutch site
 * answers "/" with a 302 to /en. That is the site behaving correctly and the test asking
 * the wrong question: it meant a visitor reading this site, not one asking for another
 * language.
 *
 * Without this the whole suite passes on an English-first install and fails on every
 * other one, which is a hard failure to read.
 *
 * A trait rather than a method on TestCase: every Laravel app already owns its TestCase,
 * and the template should not have to overwrite it to hand this over. Laravel calls
 * setUp{TraitName} for any trait a test uses, so using it is all it takes. A test that
 * means something else says so per request -- $this->get('/', ['Accept-Language' => …])
 * overrides this default.
 */
trait ReadsTheSiteLanguage
{
    protected function setUpReadsTheSiteLanguage(): void
    {
        $this->withHeader('Accept-Language', Leap::localeDefault() ?? app()->getLocale());
    }
}
