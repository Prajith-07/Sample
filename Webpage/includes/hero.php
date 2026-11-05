<?php
$animatedLogoPath = __DIR__ . '/../assets/itech_seed_grant_challenge.svg';
?>
<section class="hero page-shell" aria-labelledby="hero-title" data-reveal>
    <div class="challenge-logo" data-challenge-logo aria-label="Seed Grant Challenge logo">
        <div class="challenge-logo__art" data-logo-art>
            <?php if (is_readable($animatedLogoPath)): ?>
                <?php readfile($animatedLogoPath); ?>
            <?php else: ?>
                <img src="assets/itech_seed_grant_challenge_static_logo.svg" width="1480" height="290" alt="iTech Seed Grant Challenge — seed it. build it. launch it.">
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-grid">
        <article class="glass-panel hero-card hero-card--intro">
            <div class="hero-card__eyebrow"><span class="status-dot" aria-hidden="true"></span> PSG iTech · Seed Grant Challenge 2027</div>
            <h1 id="hero-title">Great ideas deserve <span>funding.</span></h1>
            <p class="hero-card__description">Apply for a grant of up to <strong class="text-accent">₹1,00,000</strong> to turn your idea into a working hardware or software proof of concept. Register your interdisciplinary team for the iTech Seed Grant Challenge 2027.</p>
            <div class="hero-card__actions">
                <a class="button button--primary button--large" href="signup.php">
                    Join The Challenge
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <a class="button button--quiet button--large" href="login.php">Sign in</a>
            </div>
            <div class="hero-card__footnote"><span class="hero-card__footnote-line" aria-hidden="true"></span> Interdisciplinary teams · Real problems · Working prototypes</div>
        </article>

        <article class="glass-panel challenge-card" aria-labelledby="challenge-title">
            <div class="challenge-card__topline">
                <span class="challenge-card__index">01 / THE BRIEF</span>
                <svg class="challenge-card__spark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                    <path d="M24 3.5v12M24 32.5v12M3.5 24h12m17 0h12M9.5 9.5l8.4 8.4m12.2 12.2 8.4 8.4m0-29-8.4 8.4m-12.2 12.2-8.4 8.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="24" cy="24" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                </svg>
            </div>
            <div>
                <p class="eyebrow">Define your challenge</p>
                <h2 id="challenge-title">Start with a problem worth solving.</h2>
                <p class="challenge-card__description">Choose any real industrial or societal problem and build a solution with your team. Last date to apply is December 5, 2026.</p>
            </div>
            <a class="button button--outline button--download" href="assets/iTech_Seed_Grant_Challenge_Proposal_Format.docx" download>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5v11m0 0 4-4m-4 4-4-4M4.5 16.5v3h15v-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Download Proposal Format
            </a>
            <div class="challenge-card__deadline"><span>APPLICATION WINDOW</span><strong>Nov 5 — Dec 5, 2026</strong></div>
        </article>
    </div>
</section>
