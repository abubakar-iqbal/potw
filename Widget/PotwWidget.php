<?php

namespace CoderBeams\POTW\Widget;

use XF\Http\Request;
use XF\Widget\AbstractWidget;

class PotwWidget extends AbstractWidget
{
    /**
     * @var mixed[]
     */
    protected $defaultOptions = [
        'limit' => 5,
        'snippet_length' => 250,
        'hide_images' => 1, // default is now to hide images
    ];


    public function render(): string
    {
        $limit = $this->options['limit'] ?? 5;
        $style = $this->options['style'] ?? 'full';
        $snippetLength = $this->options['snippet_length'];
        $hideImage = $this->options['hide_images'];
        $visitor = \XF::visitor();
        $options = \XF::options();

        $config = [
            'page' => 1,
            'perPage' => $limit,
            'timeLapse' => $options->cb_potw_time_lapse ?? 'week',
            'nodeIds' => $options->cb_potw_applicable_forum ?? [],
            'minimumReaction' => $options->cb_potw_reaction_limit ?? 1,
            'postsInWeeks' => $options->cb_limit_post_per_week ?? 3,
            'lastWeeks' => $options->cb_posts_weeks ?? 1,
        ];

        $weekService = new \CoderBeams\POTW\Service\Week($this->app);
        [$allPosts, $weekendArray] = $weekService->processWeeklyPosts($visitor, $config);

        return $this->renderer('cb_potw_widget', [
            'allPosts' => array_slice($allPosts, 0, $limit),
            'weekendArray' => $weekendArray,
            'snippet_length' => $snippetLength,
            'hideImage' => $hideImage,
            'style' => $style,
        ]);
    }

    /**
     * @param Phrase|null $error
     */
    public function verifyOptions(
        Request $request,
        array &$options,
        &$error = null
    ): bool {
        $options = $request->filter([
            'limit' => 'uint',
            'snippet_length' => 'uint',
            'hide_images' => 'bool',
        ]);

        if ($options['limit'] < 1) {
            $options['limit'] = 1;
        }

        return true;
    }
}
