<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Tests\TestCase;

/**
 * How much room a page leaves above its title. Every page opens with the same gap
 * below the navigation — and, since it sits there too, below the breadcrumb — no
 * matter whether the title comes from a section or from a detail page's header.
 * Pure CSS driven by tokens, so the shipped stub is what is asserted here.
 */
class TemplateSpacingTest extends TestCase
{
    private function template(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/stubs/template/resources/css/template.scss');
    }

    /**
     * A detail page's header used to open at --space-l while every section opens at
     * --space-xl, so stepping from an overview into an item moved the title up by a
     * step. Invisible until the breadcrumb gave both pages the same starting line.
     */
    public function test_a_detail_header_opens_as_wide_as_a_section_does(): void
    {
        $template = $this->template();

        foreach (['.default {', '.items {', '.item-header {'] as $selector) {
            $rule = substr($template, strpos($template, $selector));
            $rule = substr($rule, 0, strpos($rule, "\n}"));

            $this->assertMatchesRegularExpression(
                '/padding-block: var\(--space-xl\)/',
                $rule,
                "Expected {$selector} to open at --space-xl, so every page starts at the same height."
            );
        }
    }

    public function test_the_breadcrumb_keeps_its_own_band_thin(): void
    {
        // The trail is a strip between the bar and the content, not a section of its
        // own: the room above the title belongs to whatever comes after it.
        $rule = substr($this->template(), strpos($this->template(), '.breadcrumbs {'));
        $rule = substr($rule, 0, strpos($rule, "\n}"));

        $this->assertStringContainsString('padding-block: var(--space-s)', $rule);
    }
}
