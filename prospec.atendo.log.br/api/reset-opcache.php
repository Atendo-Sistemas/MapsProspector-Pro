<?php if (function_exists("opcache_reset")) { opcache_reset(); echo "OPCACHE LIMPO\n"; } else { echo "sem opcache\n"; }
