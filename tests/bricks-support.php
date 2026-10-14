<?php
/**
 * Focused checks against the active plugin; no database fixtures are written.
 * ZALOMENI_WP_LOAD=/path/to/wp-load.php php tests/bricks-support.php --group=legacy
 */

function expect_same($label, $expected, $actual) {
    if ($expected !== $actual) {
        throw new RuntimeException($label . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
    echo 'OK ' . $label . "\n";
}

function with_options(array $options, callable $check) {
    $filters = array();
    try {
        foreach ($options as $name => $value) {
            $filters[$name] = function () use ($value) { return $value; };
            add_filter('pre_option_' . $name, $filters[$name]);
        }
        return $check();
    } finally {
        foreach ($filters as $name => $callback) {
            remove_filter('pre_option_' . $name, $callback);
        }
    }
}

$group = 'legacy';
try {
    foreach (array_slice($argv, 1) as $argument) {
        if (strpos($argument, '--group=') !== 0) {
            throw new RuntimeException('Unsupported argument: ' . $argument);
        }
        $group = substr($argument, strlen('--group='));
    }
    if ($group !== 'legacy') {
        throw new RuntimeException('Unsupported group: ' . $group . '; supported: legacy');
    }

    $wp_load = getenv('ZALOMENI_WP_LOAD');
    if (!$wp_load || !is_file($wp_load) || !is_readable($wp_load)) {
        throw new RuntimeException('Set ZALOMENI_WP_LOAD to an existing readable WordPress wp-load.php file.');
    }

    $_GET = $_POST = $_REQUEST = array();
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    // A CLI typography check must not spawn scheduled work during bootstrap.
    if (!defined('DISABLE_WP_CRON')) {
        define('DISABLE_WP_CRON', true);
    }
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', false);
    }
    require $wp_load;
    if (!class_exists('Zalomeni', false)) {
        throw new RuntimeException('The active Zalomeni plugin was not loaded by WordPress.');
    }
    echo 'WordPress ' . $wp_version . '; PHP ' . PHP_VERSION . "\n";

    $disabled = array(
        'zalomeni_prepositions' => '',
        'zalomeni_prepositions_list' => '',
        'zalomeni_conjunctions' => '',
        'zalomeni_conjunctions_list' => '',
        'zalomeni_abbreviations' => '',
        'zalomeni_abbreviations_list' => '',
        'zalomeni_between_number_and_unit' => '',
        'zalomeni_between_number_and_unit_list' => '',
        'zalomeni_spaces_in_scales' => '',
        'zalomeni_space_between_numbers' => '',
        'zalomeni_space_after_ordered_number' => '',
        'zalomeni_custom_terms' => '',
        'zalomeni_matches' => array(),
        'zalomeni_replacements' => array(),
    );

    // Original-plugin characterization values captured before runtime edits,
    // from zalomeni.php at e05c652 on WordPress 7.1.3 / PHP 8.4.24.
    // Keep compiler arrays literal: stored fixtures alone cannot detect compiler changes.
    $fixtures = array(
        'words' => array(
            'options' => array(
                'zalomeni_prepositions' => 'on',
                'zalomeni_prepositions_list' => 'k, s, v, z',
                'zalomeni_conjunctions' => 'on',
                'zalomeni_conjunctions_list' => 'a, i, o, u',
            ),
            'matches' => array(
                'words' => '@(^|;| |&nbsp;|\(|\n)(?<!&#8217;)(?<!&#39;)(?<!&#039;)(?<!&rsquo;)(?<!&apos;)(?<!&#x2019;)(?<!&#x27;)(k|s|v|z|a|i|o|u) @i',
            ),
            'replacements' => array('words' => '$1$2&nbsp;'),
            'cases' => array(
                'legacy HTML' => array('<p>V lese a v zahradě.</p>', '<p>V&nbsp;lese a&nbsp;v&nbsp;zahradě.</p>'),
                'protected legacy' => array('<code>v lese</code>', '<code>v lese</code>'),
                'legacy apostrophe' => array('America&#8217;s Response', 'America&#8217;s Response'),
                'literal shortcode protection' => array('[code]v lese[/code] v lese', '[code]v lese[/code] v&nbsp;lese'),
            ),
        ),
        'numeric' => array(
            'options' => array(
                'zalomeni_between_number_and_unit' => 'on',
                'zalomeni_between_number_and_unit_list' => 'kg, Kč, m²',
                'zalomeni_spaces_in_scales' => 'on',
                'zalomeni_space_between_numbers' => 'on',
                'zalomeni_space_after_ordered_number' => 'on',
            ),
            'matches' => array(
                'units' => '@(\d) (kg|kč|m²)(^|[;\.!:]| |&nbsp;|\?|\n|\)|<|\010|\013|$)@i',
                'numbers' => '@(\d) (\d)@i',
                'scales' => '@(\d) : (\d)@i',
                'orders' => '@(\d\.) ([0-9a-záčďéěíňóřšťúýž])@',
            ),
            'replacements' => array(
                'units' => '$1&nbsp;$2$3',
                'numbers' => '$1&nbsp;$2',
                'scales' => '$1&nbsp;:&nbsp;$2',
                'orders' => '$1&nbsp;$2',
            ),
            'cases' => array(
                'legacy units' => array('5 kg, 10 Kč a 2 m².', '5 kg, 10&nbsp;Kč a 2&nbsp;m².'),
                'legacy numbers' => array('800 123 456', '800&nbsp;123&nbsp;456'),
                'legacy ordinals' => array('1. ledna a 2. 3. 2026', '1.&nbsp;ledna a 2.&nbsp;3.&nbsp;2026'),
                'legacy scales' => array('1 : 50 000', '1&nbsp;:&nbsp;50&nbsp;000'),
            ),
        ),
        'custom' => array(
            'options' => array(
                'zalomeni_custom_terms' => "Digit \\d\nNondigit \\D\nWord \\w\nNonword \\W\nSpace \\s\nNonspace \\S\nLiteral (x)+",
            ),
            'matches' => array(
                'customterm1' => '/(Digit) (\d)/i',
                'customterm2' => '/(Nondigit) (\D)/i',
                'customterm3' => '/(Word) (\w)/i',
                'customterm4' => '/(Nonword) (\W)/i',
                'customterm5' => '/(Space) (\s)/i',
                'customterm6' => '/(Nonspace) (\S)/i',
                'customterm7' => '/(Literal) (\(x\)\+)/i',
            ),
            'replacements' => array(
                'customterm1' => '$1&nbsp;$2',
                'customterm2' => '$1&nbsp;$2',
                'customterm3' => '$1&nbsp;$2',
                'customterm4' => '$1&nbsp;$2',
                'customterm5' => '$1&nbsp;$2',
                'customterm6' => '$1&nbsp;$2',
                'customterm7' => '$1&nbsp;$2',
            ),
            'cases' => array(
                'supported custom escapes and literal metacharacters' => array(
                    'Digit 7; Nondigit A; Word x; Nonword !; Space  ; Nonspace Z; Literal (x)+',
                    'Digit&nbsp;7; Nondigit&nbsp;A; Word&nbsp;x; Nonword&nbsp;!; Space&nbsp; ; Nonspace&nbsp;Z; Literal&nbsp;(x)+',
                ),
            ),
        ),
        'empty' => array(
            'options' => array(),
            'matches' => array(),
            'replacements' => array(),
            'cases' => array(
                'empty matches' => array('<p>v lese 5 kg 1 : 50 000</p>', '<p>v lese 5 kg 1 : 50 000</p>'),
            ),
        ),
    );

    $matches_compiler = new ReflectionMethod('Zalomeni', 'prepare_matches');
    $replacements_compiler = new ReflectionMethod('Zalomeni', 'prepare_replacements');
    $matches_compiler->setAccessible(true);
    $replacements_compiler->setAccessible(true);
    foreach ($fixtures as $name => $fixture) {
        $options = array_merge($disabled, $fixture['options'], array(
            'zalomeni_matches' => $fixture['matches'],
            'zalomeni_replacements' => $fixture['replacements'],
        ));
        with_options($options, function () use ($name, $fixture, $matches_compiler, $replacements_compiler) {
            expect_same($name . ' default matches compiler', $fixture['matches'], $matches_compiler->invoke(null));
            expect_same($name . ' default replacements compiler', $fixture['replacements'], $replacements_compiler->invoke(null));
            foreach ($fixture['cases'] as $label => $case) {
                expect_same($label, $case[1], Zalomeni::texturize($case[0]));
            }
            if ($name === 'words') {
                $nbsp = "\xC2\xA0";
                expect_same('legacy plain', 'v'.$nbsp.'lese', Zalomeni::texturize_plain('v lese'));
                expect_same('plain nonstring', array('v lese'), Zalomeni::texturize_plain(array('v lese')));
            }
        });
    }

    with_options(array_merge($disabled, array(
        'zalomeni_matches' => array('/[/'),
        'zalomeni_replacements' => array('changed'),
    )), function () {
        expect_same('malformed pattern fallback', '<p>v lese</p>', Zalomeni::texturize('<p>v lese</p>'));
    });

    with_options(array_merge($disabled, array(
        'zalomeni_matches' => array('/(*NO_START_OPT)(*NO_AUTO_POSSESS)(X)(a+c)|(a+)+b/'),
        'zalomeni_replacements' => array('$2'),
    )), function () {
        $previous_limit = ini_get('pcre.backtrack_limit');
        try {
            if (ini_set('pcre.backtrack_limit', '50') === false) {
                throw new RuntimeException('Cannot set pcre.backtrack_limit for the second-pass failure fixture.');
            }
            $input = 'Xaaaaaaaaaaaaaac';
            $matches = get_option('zalomeni_matches');
            $replacements = get_option('zalomeni_replacements');
            $first = preg_replace($matches, $replacements, $input);
            expect_same('second-pass fixture first result', 'aaaaaaaaaaaaaac', $first);
            expect_same('second-pass fixture first error', PREG_NO_ERROR, preg_last_error());
            $second = preg_replace($matches, $replacements, $first);
            expect_same('second-pass fixture second result', null, $second);
            expect_same('second-pass fixture second error', PREG_BACKTRACK_LIMIT_ERROR, preg_last_error());
            expect_same('second-pass failure restores complete original input', $input, Zalomeni::texturize($input));
        } finally {
            ini_set('pcre.backtrack_limit', $previous_limit);
        }
        expect_same('PCRE limit restored', $previous_limit, ini_get('pcre.backtrack_limit'));
    });

    echo 'PASS legacy' . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $group . ': ' . $error->getMessage() . "\n");
    exit(1);
}
