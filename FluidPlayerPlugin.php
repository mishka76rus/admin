<?php
class FluidPlayerPlugin {

    public static $index = 0;

    private static function loadAssets()
    {
        wp_enqueue_script(
            'fluid-player-js',
            self::FP_CDN_CURRENT_URL . '/fluidplayer.min.js',
            [],
            false
        );
        wp_enqueue_style('fluid-player-css', self::FP_CDN_CURRENT_URL . '/fluidplayer.min.css');
    }

    public static function init()
    {
        static::loadAssets();
        static::initShortcodeToJSMapping();

        add_shortcode('fluid-player', array('FluidPlayerPlugin', 'shortcodeSimple'));
        add_shortcode('fluid-player-extended', array('FluidPlayerPlugin', 'shortcodeExtended'));
        add_shortcode('fluid-player-html-block', array('FluidPlayerPlugin', 'shortcodeHtmlBlock'));
        add_shortcode('fluid-player-multi-res-video', array('FluidPlayerPlugin', 'shortcodeHtmlBlock'));

        //Disabling smart quotes filter for shortcode content
        add_filter('no_texturize_shortcodes', function () {
            $shortcodes[] = 'fluid-player-multi-res-video';
            $shortcodes[] = 'fluid-player-html-block';
            $shortcodes[] = 'fluid-player-extended';
            $shortcodes[] = 'fluid-player';

            return $shortcodes;
        });

        //Disabling line breaks and paragraphs from shortcode content. Could have side effects!
        remove_filter('the_content', 'wpautop');
        remove_filter('the_excerpt', 'wpautop');
    }

    /**
     * @param array $attrs
     * @param string $content
     *
     * @return string
     */
    public static function shortcodeHtmlBlock($attrs, $content)
    {
        return '';
    }

    /**
     * @param array $attrs
     *
     * @return string
     */
    public static function shortcodeSimple($attrs)
    {
        $params = shortcode_atts([
            //See https://exadsdev.atlassian.net/browse/ESR-1783
            static::VAST_FILE        => '',
            static::VTT_FILE         => '',
            static::VTT_SPRITE       => '',
            static::FP_OPTIONS_AUTOPLAY => 'false',
            static::FP_OPTIONS_DOWNLOAD      => 'false',
            static::FP_OPTIONS_PLAYBACK      => 'false',
            static::FP_OPTIONS_RESPONSIVE    => 'false',
            static::FP_OPTIONS_POSTER_IMAGE   => 'false',
            static::FP_VIDEO_SOURCES => static::prepareVideoSources([
                [
                    'label' => 'HD',
                    'url'   => $attrs['video']
                ]
            ], []),
            static::FP_LAYOUT        => static::FP_LAYOUT_DEFAULT_VALUE,
        ], $attrs);

        return static::generateContent(static::SCRIPT_CONTENT, $params);
    }

    /**
     * @param array $attrs
     * @param string $content
     *
     * @return string
     */
    public static function shortcodeExtended($attrs, $content)
    {
        $params = shortcode_atts([
            //See https://exadsdev.atlassian.net/browse/ESR-1783
            static::VAST_FILE => '',
            static::VTT_FILE  => '',
            static::VTT_SPRITE => '',
            static::FP_LAYOUT  => static::FP_LAYOUT_DEFAULT_VALUE,
            static::FP_OPTIONS_AUTOPLAY      => 'false',
            static::FP_OPTIONS_DOWNLOAD      => 'false',
            static::FP_OPTIONS_PLAYBACK      => 'false',
            static::FP_OPTIONS_LOGO          => plugin_dir_url(__FILE__) . 'web/images/yourlogo.png',
            static::FP_OPTIONS_LOGO_POSITION => 'top left',
            static::FP_OPTIONS_LOGO_OPACITY  => '1',
            static::FP_OPTIONS_LOGO_HYPER => null,
            static::FP_OPTIONS_AD_TEXT       => '',
            static::FP_OPTIONS_AD_TEXT_CTA   => '',
            static::FP_OPTIONS_RESPONSIVE    => 'false',
            static::FP_OPTIONS_POSTER_IMAGE  => 'false',
            static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH    => 100,
            static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT    => 100,
        ], $attrs);

        if (has_shortcode($content, 'fluid-player-html-block')) {
            $htmlBlock = static::extractShortCode($content, 'fluid-player-html-block');
            $params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK] = $htmlBlock;
        }

        $content = html_entity_decode($content);
        if (has_shortcode($content, 'fluid-player-multi-res-video')) {
            $multiResVideo = static::extractShortCode($content, 'fluid-player-multi-res-video');
        } else {
            //Keeping the plugin compatible with the previous extended shortcode format (no nested shortcode)
            $multiResVideo = do_shortcode($content);
        }
        $params[static::FP_VIDEO_SOURCES] = static::prepareVideoSources(
            static::extractVideos(html_entity_decode($multiResVideo)),
            [['label' => '720', 'url' => self::FP_CDN_ROOT_URL . '/videos/1.3/fluidplayer_480.mp4']]
        );

        return static::generateContent(static::SCRIPT_CONTENT, $params);
    }

    private static function getLayoutOptions($params)
    {
        $options = static::getDefaultOptions();

        if (null == $params[static::VTT_FILE] || null == $params[static::VTT_SPRITE]) {
            unset($options[static::FP_TIMELINE_OBJ]);
        }
        if (isset($params[static::FP_LAYOUT])) {
            $options[static::FP_LAYOUT] = $params[static::FP_LAYOUT];
        }

        //Autoplay
        if (isset($params[static::FP_OPTIONS_AUTOPLAY_JS]) && $params[static::FP_OPTIONS_AUTOPLAY_JS] !== 'false') {
            $options[static::FP_OPTIONS_AUTOPLAY_JS] = true;
        }

        //allowDownload
        if (isset($params[static::FP_OPTIONS_DOWNLOAD_JS]) && $params[static::FP_OPTIONS_DOWNLOAD_JS] !== 'false') {
            $options[static::FP_OPTIONS_DOWNLOAD_JS] = true;
        }

        //playbackRateEnabled
        if (isset($params[static::FP_OPTIONS_PLAYBACK_JS]) && $params[static::FP_OPTIONS_PLAYBACK_JS] !== 'false') {
            $options[static::FP_OPTIONS_PLAYBACK_JS] = true;
        }

        //Logo
        if (isset($params[static::FP_OPTIONS_LOGO_JS])) {
            $options[static::FP_OPTIONS_LOGO_JS] = [];
               $options[static::FP_OPTIONS_LOGO_JS]['imageUrl'] = $params[static::FP_OPTIONS_LOGO_JS];

            //logoPosition
            if (isset($params[static::FP_OPTIONS_LOGO_POSITION_JS])) {
                $options[static::FP_OPTIONS_LOGO_JS]['position'] = $params[static::FP_OPTIONS_LOGO_POSITION_JS];
            }

            //logoOpacity
            if (isset($params[static::FP_OPTIONS_LOGO_OPACITY_JS])) {
                $options[static::FP_OPTIONS_LOGO_JS]['opacity'] = $params[static::FP_OPTIONS_LOGO_OPACITY_JS];
            }

            if (isset($params[static::FP_OPTIONS_LOGO_HYPER_JS])) {
                $options[static::FP_OPTIONS_LOGO_JS]['clickUrl'] = $params[static::FP_OPTIONS_LOGO_HYPER_JS];
            } else {
                $options[static::FP_OPTIONS_LOGO_JS]['clickUrl'] = null;
            }
        }

        //htmlOnPauseBlock
        if (isset($params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS])) {
            $options[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS] = [
                'html' => $params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS],
            ];

            if (isset($params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH_JS])) {
                $options[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS]['width'] = (int)$params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH_JS];
            }

            if (isset($params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT_JS])) {
                $options[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS]['height'] = (int)$params[static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT_JS];
            }
        }

        //responsive
        if (isset($params[static::FP_OPTIONS_RESPONSIVE_JS]) && $params[static::FP_OPTIONS_RESPONSIVE_JS] !== 'false') {
            $options[static::FP_OPTIONS_RESPONSIVE_JS] = true;
        }

        //posterImage
        if (isset($params[static::FP_OPTIONS_POSTER_IMAGE_JS]) && $params[static::FP_OPTIONS_POSTER_IMAGE_JS] !== 'false') {
            $options[static::FP_OPTIONS_POSTER_IMAGE_JS] = $params[static::FP_OPTIONS_POSTER_IMAGE_JS];
        }

        $options[static::FP_TIMELINE_OBJ][static::FP_TIMELINE_FILE]   = $params[static::VTT_FILE];
        $options[static::FP_TIMELINE_OBJ][static::FP_TIMELINE_SPRITE] = $params[static::VTT_SPRITE];

        return $options;
    }

    private static function getVastOptions($params)
    {
        $options = static::getDefaultOptions();
        unset($options[static::FP_LAYOUT]);
        unset($options[static::FP_TIMELINE_OBJ]);

        //VAST
        if (isset($params[static::VAST_FILE])) {
            $options['adList'] = [
                [
                    'roll' => 'preRoll',
                    'vastTag' => $params[static::VAST_FILE]
                ],
            ];
        }

        //adText
        if (isset($params[static::FP_OPTIONS_AD_TEXT_JS])) {
            $options[static::FP_OPTIONS_AD_TEXT_JS] = $params[static::FP_OPTIONS_AD_TEXT_JS];
        }

        //adCTAText
        if (isset($params[static::FP_OPTIONS_AD_TEXT_CTA_JS])) {
            $options[static::FP_OPTIONS_AD_TEXT_CTA_JS] = $params[static::FP_OPTIONS_AD_TEXT_CTA_JS];
        }

        return $options;
    }

    public static function getDefaultOptions()
    {
        return [
            static::FP_TIMELINE_OBJ => [
                static::FP_TIMELINE_FILE   => '',
                static::FP_TIMELINE_SPRITE => '',
                static::FP_TIMELINE_TYPE   => 'VTT',
            ],
            static::FP_LAYOUT       => static::FP_LAYOUT_DEFAULT_VALUE
        ];
    }

    /**
     * @param string $content
     *
     * @return array
     */
    private static function extractVideos($content)
    {
        $content = preg_replace('/\s+/', ' ', $content);
        preg_match('/(\[.*\])/s', $content, $matches);

        return json_decode($matches[0], true);
    }

    private static function getVideoSourceString($video)
    {
        return '<source title="' . $video['label'] . '" src=' . $video['url'] . ' type="video/mp4" />';
    }

    /**
     * @param array[string] $videos
     * @param string $fallbackVideo
     *
     * @return string
     */
    private static function prepareVideoSources($videos, $fallbackVideo)
    {

        if (is_null($videos)) {
            return static::getVideoSourceString($fallbackVideo);
        }

        $videosCode = [];
        foreach ($videos as $video) {
            $videosCode[] = static::getVideoSourceString($video);
        }

        return join('', $videosCode);
    }

    /**
     * @param string $mold
     * @param array $params
     *
     * @return mixed
     */
    private static function generateContent($mold, $params)
    {
        $shortcodeContent = str_replace(
            [
                '{' . static::FP_ID . '}',
                '{' . static::FP_VIDEO . '}',
                '{' . static::FP_VIDEO_SOURCES . '}',
                '{' . static::FP_LAYOUT_OPTIONS . '}',
                '{' . static::FP_VAST_OPTIONS . '}',
                //'{' . static::VAST_FILE . '}',
                //'{' . static::FP_OPTIONS . '}',
            ],
            [
                static::$index ++,
                $params[static::FP_VIDEO],
                $params[static::FP_VIDEO_SOURCES],
                json_encode(static::getLayoutOptions(static::getRemappedParams($params))),
                json_encode(static::getVastOptions(static::getRemappedParams($params))),
                //$params[static::VAST_FILE],
                //json_encode(static::getPlayerOptions(static::getRemappedParams($params))),
            ],
            $mold
        );

        return $shortcodeContent;
    }

    /**
     * @param string $content
     * @param string $shortcodeTag
     *
     * @return string
     */
    private static function extractShortCode($content, $shortcodeTag)
    {
        $content = trim(preg_replace('/\s\s+/', ' ', $content));
        preg_match("/\[" . $shortcodeTag . "\](.*)\[\/" . $shortcodeTag . "\]/", $content, $output_array);

        return $output_array[1];
    }

    private static function initShortcodeToJSMapping()
    {
        static::$shortcodeToJSMapping = [
            static::FP_LAYOUT                             => static::FP_LAYOUT,
            static::FP_OPTIONS_AUTOPLAY                   => static::FP_OPTIONS_AUTOPLAY_JS,
            static::FP_OPTIONS_DOWNLOAD                   => static::FP_OPTIONS_DOWNLOAD_JS,
            static::FP_OPTIONS_PLAYBACK                   => static::FP_OPTIONS_PLAYBACK_JS,
            static::FP_OPTIONS_LOGO                       => static::FP_OPTIONS_LOGO_JS,
            static::FP_OPTIONS_LOGO_POSITION              => static::FP_OPTIONS_LOGO_POSITION_JS,
            static::FP_OPTIONS_LOGO_OPACITY               => static::FP_OPTIONS_LOGO_OPACITY_JS,
            static::FP_OPTIONS_LOGO_HYPER                 => static::FP_OPTIONS_LOGO_HYPER_JS,
            static::FP_OPTIONS_AD_TEXT                    => static::FP_OPTIONS_AD_TEXT_JS,
            static::FP_OPTIONS_AD_TEXT_CTA                => static::FP_OPTIONS_AD_TEXT_CTA_JS,
            static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK        => static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS,
            static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH  => static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH_JS,
            static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT => static::FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT_JS,
            static::FP_OPTIONS_RESPONSIVE                 => static::FP_OPTIONS_RESPONSIVE_JS,
            static::FP_OPTIONS_POSTER_IMAGE               => static::FP_OPTIONS_POSTER_IMAGE_JS,
            static::FP_VAST_FILE                          => static::FP_VAST_FILE_JS,
        ];
    }

    /**
     * @param array $params
     *
     * @return array
     */
    private static function getRemappedParams($params)
    {
        $remappedParams = [];
        foreach ($params as $key => $value) {
            $remappedParams[static::$shortcodeToJSMapping[$key]] = $value;
        }

        return $remappedParams;
    }

    const FP_CDN_ROOT_URL = 'https://cdn.fluidplayer.com';
    const FP_CDN_CURRENT_URL = 'https://cdn.fluidplayer.com/v2/current';

    const FP_ID = 'id';
    const FP_VIDEO = 'video';
    const FP_VIDEO_SOURCES = 'video_sources';
    const FP_LAYOUT = 'layout';
    const FP_LAYOUT_DEFAULT_VALUE = 'default';
    const FP_LAYOUT_OPTIONS = 'layout_options';
    const FP_VAST_OPTIONS = 'vast_options';

    const FP_OPTIONS_AUTOPLAY = 'auto-play';
    const FP_OPTIONS_DOWNLOAD = 'allow-download';
    const FP_OPTIONS_PLAYBACK = 'playback-speed-control';
    const FP_OPTIONS_LOGO = 'logo';
    const FP_OPTIONS_LOGO_POSITION = 'logo-position';
    const FP_OPTIONS_LOGO_HYPER = 'logo-hyperlink';
    const FP_OPTIONS_LOGO_OPACITY = 'logo-opacity';
    const FP_OPTIONS_AD_TEXT = 'ad-text';
    const FP_OPTIONS_AD_TEXT_CTA = 'ad-cta-text';
    const FP_OPTIONS_HTML_ON_PAUSE_BLOCK = 'html-on-pause-block';
    const FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH = 'html-on-pause-block-width';
    const FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT = 'html-on-pause-block-height';
    const FP_OPTIONS_RESPONSIVE = 'responsive'; //Keeping the old shortcode param for legacy reasons
    const FP_OPTIONS_POSTER_IMAGE = 'poster-image';
    const FP_VAST_FILE = 'vast_file';

    const FP_OPTIONS_AUTOPLAY_JS = 'autoPlay';
    const FP_OPTIONS_DOWNLOAD_JS = 'allowDownload';
    const FP_OPTIONS_PLAYBACK_JS = 'playbackRateEnabled';
    const FP_OPTIONS_LOGO_JS = 'logo'; //TODO: update this
    const FP_OPTIONS_LOGO_POSITION_JS = 'logoPosition';//TODO: update this
    const FP_OPTIONS_LOGO_OPACITY_JS = 'logoOpacity';//TODO: update this
    const FP_OPTIONS_LOGO_HYPER_JS = 'clickUrl';//TODO: update this
    const FP_OPTIONS_AD_TEXT_JS = 'adText';//TODO: Move to Vast
    const FP_OPTIONS_AD_TEXT_CTA_JS = 'adCTAText';//TODO: Move to Vast
    const FP_OPTIONS_HTML_ON_PAUSE_BLOCK_JS = 'htmlOnPauseBlock';//TODO update this
    const FP_OPTIONS_HTML_ON_PAUSE_BLOCK_WIDTH_JS = 'htmlOnPauseBlockWidth';//TODO: Update this
    const FP_OPTIONS_HTML_ON_PAUSE_BLOCK_HEIGHT_JS = 'htmlOnPauseBlockHeight';//TODO: Update this
    const FP_OPTIONS_RESPONSIVE_JS = 'fillToContainer';
    const FP_OPTIONS_POSTER_IMAGE_JS = 'posterImage';
    const FP_VAST_FILE_JS = 'vast_file';

    static $shortcodeToJSMapping = array();

    const FP_TIMELINE_OBJ = 'timelinePreview';
    const FP_TIMELINE_FILE = 'file';
    const FP_TIMELINE_SPRITE = 'sprite';
    const FP_TIMELINE_TYPE = 'type';

    const VAST_FILE = 'vast_file';
    const VTT_FILE = 'vtt_file';
    const VTT_SPRITE = 'vtt_sprite';


    const SCRIPT_CONTENT = <<<SCRIPT
<video id='fp-video-{id}' controls>
    {video_sources}
</video>

<script type="text/javascript">

var fluidPlayerPlugin{id} = function() {
    var testVideo = fluidPlayer(
        'fp-video-{id}',
        /* '{vast_file}', fp_options} */
        {
            layoutControls: {layout_options},
            vastOptions: {vast_options}
        }
    );
};

(function defer() {
    if (typeof(fluidPlayer) != 'undefined') {
        fluidPlayerPlugin{id}();
    } else {
        setTimeout(defer, 50);
    }
})();
</script>
SCRIPT;
}
