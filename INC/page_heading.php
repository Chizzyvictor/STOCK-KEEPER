<?php
/** Shared page heading. Set title/description and optional icon/actions/meta before including. */
$_pageHeadingTitle = (string) ($pageHeadingTitle ?? '');
$_pageHeadingDescription = (string) ($pageHeadingDescription ?? '');
$_pageHeadingIcon = trim((string) ($pageHeadingIcon ?? ''));
$_pageHeadingActions = (string) ($pageHeadingActions ?? '');
$_pageHeadingMeta = (string) ($pageHeadingMeta ?? '');
$headingClass = trim('page-heading ' . (string) ($pageHeadingClass ?? ''));
$titleClass = trim('h3 fw-bold mb-1 ' . (string) ($pageHeadingTitleClass ?? ''));
$descriptionClass = trim('text-muted mb-0 ' . (string) ($pageHeadingDescriptionClass ?? ''));
?>
<header class="<?= htmlspecialchars($headingClass, ENT_QUOTES, 'UTF-8') ?> d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="<?= htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($_pageHeadingIcon !== ''): ?><i class="<?= htmlspecialchars($_pageHeadingIcon, ENT_QUOTES, 'UTF-8') ?> me-2" aria-hidden="true"></i><?php endif; ?>
            <?= htmlspecialchars($_pageHeadingTitle, ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <?php if ($_pageHeadingDescription !== ''): ?><p class="<?= htmlspecialchars($descriptionClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($_pageHeadingDescription, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($_pageHeadingMeta !== ''): ?><div class="mt-1"><?= $_pageHeadingMeta ?></div><?php endif; ?>
    </div>
    <?php if ($_pageHeadingActions !== ''): ?><div class="page-heading-actions d-flex flex-wrap align-items-center gap-2"><?= $_pageHeadingActions ?></div><?php endif; ?>
</header>
<?php unset($_pageHeadingTitle, $_pageHeadingDescription, $_pageHeadingIcon, $_pageHeadingActions, $_pageHeadingMeta, $headingClass, $titleClass, $descriptionClass); ?>
