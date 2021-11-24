<?php declare(strict_types=1);

namespace ILIAS\UI\examples\Item\Contribution;

function with_critical_identifier()
{
    global $DIC;

    return $DIC->ui()->renderer()->render(
        $DIC->ui()->factory()->item()->contribution(
            'a little test contribution'
        )->withIdentifier('noid"><script>alert(\'CRITICAL\')</script')
    );
}
