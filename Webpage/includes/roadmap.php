<?php
$milestones = [
    [
        'number' => '01',
        'name' => 'APPLICATION',
        'description' => 'Submit a 3-page proposal with your PoC plan, budget, and mentor details.',
        'timeline' => 'Nov 5 – Dec 5, 2026',
    ],
    [
        'number' => '02',
        'name' => 'SCREENING',
        'description' => 'Panel reviews proposals for eligibility, plagiarism, and rubric alignment.',
        'timeline' => 'Dec 1 – Dec 15, 2026',
    ],
    [
        'number' => '03',
        'name' => 'IDEA VALIDATION WORKSHOP',
        'description' => 'Refine your technical scope, market impact, and budget with industry experts.',
        'timeline' => 'Early Dec 2026',
    ],
    [
        'number' => '04',
        'name' => 'PITCH DAY',
        'description' => 'Present your solution before an expert jury for scoring and grant selection.',
        'timeline' => 'Mid-Dec 2026',
    ],
    [
        'number' => '05',
        'name' => 'SANCTION',
        'description' => 'Sign agreements, submit startup undertakings, and unlock 50% initial funding.',
        'timeline' => 'Dec 2026 – Jan 2027',
    ],
    [
        'number' => '06',
        'name' => 'EXECUTION & DEMO DAY',
        'description' => '6-month build phase with monthly reviews, remaining payouts, and final PoC showcase.',
        'timeline' => 'Jan 1 – Jun 30, 2027',
    ],
];
?>
<section class="roadmap-section section-shell" aria-labelledby="roadmap-title" data-reveal>
    <div class="section-heading section-heading--roadmap">
        <p class="eyebrow">A clear path from ambition to proof</p>
        <h2 id="roadmap-title">From Idea <span class="text-accent">To Demo</span></h2>
        <p class="section-heading__support">Roadmap to Seed Grant Challenge</p>
    </div>

    <div class="roadmap-track">
        <svg class="roadmap-wave" viewBox="0 0 1200 360" preserveAspectRatio="none" fill="none" aria-hidden="true">
            <defs>
                <linearGradient id="roadmap-gradient" x1="0" y1="0" x2="1200" y2="0" gradientUnits="userSpaceOnUse">
                    <stop stop-color="var(--roadmap-line-start)"/>
                    <stop offset=".5" stop-color="var(--accent)"/>
                    <stop offset="1" stop-color="var(--roadmap-line-end)"/>
                </linearGradient>
            </defs>
            <path class="roadmap-wave__glow" d="M100 80 C155 80 245 230 300 230 S445 80 500 80 S645 230 700 230 S845 80 900 80 S1045 230 1100 230"/>
            <path class="roadmap-wave__line" d="M100 80 C155 80 245 230 300 230 S445 80 500 80 S645 230 700 230 S845 80 900 80 S1045 230 1100 230"/>
            <g class="roadmap-wave__nodes">
                <circle cx="100" cy="80" r="7"/><circle cx="300" cy="230" r="7"/>
                <circle cx="500" cy="80" r="7"/><circle cx="700" cy="230" r="7"/>
                <circle cx="900" cy="80" r="7"/><circle cx="1100" cy="230" r="7"/>
            </g>
        </svg>

        <ol class="roadmap-list">
            <?php foreach ($milestones as $index => $milestone): ?>
                <?php $isAbove = $index % 2 === 0; ?>
                <li class="milestone <?= $isAbove ? 'milestone--above' : 'milestone--below' ?>" style="--milestone-index: <?= (int) $index ?>;">
                    <span class="milestone__marker" aria-hidden="true"><span><?= htmlspecialchars($milestone['number'], ENT_QUOTES, 'UTF-8') ?></span></span>
                    <article class="milestone__card glass-panel">
                        <div class="milestone__topline">
                            <span class="milestone__number"><?= htmlspecialchars($milestone['number'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="milestone__rule" aria-hidden="true"></span>
                        </div>
                        <h3><?= htmlspecialchars($milestone['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($milestone['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="milestone__date">
                            <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2.25" y="3.5" width="11.5" height="10" rx="1.5"/><path d="M5 2v3m6-3v3M2.5 6.5h11"/></svg>
                            <span><?= htmlspecialchars($milestone['timeline'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <p class="roadmap-note"><span class="roadmap-note__mark" aria-hidden="true">i</span> Please confirm the schedule: the Idea Validation Workshop is listed for early December while Screening runs through December 15. The proposal format places Demo Day in the second week of June, while this final stage spans January 1 through June 30.</p>
</section>
