<?php
/** Shared metric card. Configure the $statCard* values before including. */
$colClass = trim((string) ($statCardColClass ?? 'col-sm-6 col-xl-3 mb-3'));
$cardClass = trim((string) ($statCardClass ?? 'card shadow-sm border-0 h-100'));
$label = (string) ($statCardLabel ?? '');
$valueId = trim((string) ($statCardValueId ?? ''));
$value = (string) ($statCardValue ?? '—');
$valueClass = trim((string) ($statCardValueClass ?? 'h4 mb-0'));
$note = (string) ($statCardNote ?? '');
$noteId = trim((string) ($statCardNoteId ?? ''));
$iconClass = trim((string) ($statCardIconClass ?? ''));
$tag = in_array(($statCardTag ?? 'div'), ['div', 'h3', 'h4', 'strong'], true) ? ($statCardTag ?? 'div') : 'div';
?>
<div class="<?= htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8') ?>">
    <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="text-muted small mb-1"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                <<?= $tag ?><?= $valueId !== '' ? ' id="' . htmlspecialchars($valueId, ENT_QUOTES, 'UTF-8') . '"' : '' ?> class="<?= htmlspecialchars($valueClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></<?= $tag ?>>
                <?php if ($note !== ''): ?><small<?= $noteId !== '' ? ' id="' . htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') . '"' : '' ?> class="d-block text-muted mt-1"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
            </div>
            <?php if ($iconClass !== ''): ?><i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><?php endif; ?>
        </div>
    </article>
</div>
<?php unset($colClass, $cardClass, $label, $valueId, $value, $valueClass, $note, $noteId, $iconClass, $tag); ?>