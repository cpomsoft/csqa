<?php
/**
 * CryoSat-2 Performance Monitoring (CSQA) portal configuration
 *
 * CSQA_DATA_DIR is the output directory of the CSQA QCV processing tools
 * (cpom.altimetry.projects.csqa, csqa_config.yaml:output_dir). It is outside the web root:
 * plots are served through plot.php and statistics are read by the PHP pages.
 * It can be overridden with the CSQA_DATA_DIR environment variable (ie SetEnv in Apache).
 */

define('CSQA_DATA_DIR', rtrim(getenv('CSQA_DATA_DIR') ?: '/raid6/www/csqa_data', '/'));

define('CSQA_SITE_TITLE', 'CryoSat-2 Performance Monitoring');

// contact shown in the page footer
define('CSQA_CONTACT_EMAIL', 'a.muir@ucl.ac.uk');

// a cycle is marked as partial when its input data covers less than this fraction of the cycle
define('CSQA_PARTIAL_COVERAGE_FRACTION', 0.95);

// external libraries (CDN)
define('CSQA_BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
define('CSQA_BOOTSTRAP_JS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js');
define('CSQA_FONTAWESOME_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
define('CSQA_PLOTLY_JS', 'https://cdn.plot.ly/plotly-2.32.0.min.js');
