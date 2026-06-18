import {
    Application,
    Assets,
    Container,
    Graphics,
    Sprite,
    Text,
} from 'pixi.js';

const HIT_EVENTS = new Set(['hit', 'critical_hit', 'interactive_hit', 'interactive_critical_hit']);
const CRITICAL_EVENTS = new Set(['critical_hit', 'interactive_critical_hit']);
const MISS_EVENTS = new Set(['miss', 'interactive_miss']);
const ITEM_EVENTS = new Set(['self_repair', 'interactive_item_used']);
const FINISH_EVENTS = new Set(['battle_finished', 'interactive_battle_finished']);

const wait = (duration) => new Promise((resolve) => window.setTimeout(resolve, duration));

class BattleVisualizer {
    constructor(element) {
        this.element = element;
        this.viewport = element.querySelector('[data-battle-canvas]');
        this.fallback = element.querySelector('[data-battle-canvas-fallback]');
        this.announcer = element.querySelector('[data-battle-announcer]');
        this.roundNode = element.querySelector('[data-battle-scene-round]');
        this.configNode = element.querySelector('[data-battle-scene-config]');
        this.pauseButton = element.querySelector('[data-battle-motion-toggle]');
        this.skipButton = element.querySelector('[data-battle-animation-skip]');
        this.latestEventId = Number(element.dataset.battleLatestEventId || 0);
        this.queue = [];
        this.fighters = new Map();
        this.processing = false;
        this.paused = false;
        this.skipRequested = false;
        this.destroyed = false;
        this.resizeObserver = null;
        this.app = null;
        this.background = null;
        this.effects = null;
        this.config = null;
    }

    async init() {
        if (!this.viewport || !this.configNode) {
            return;
        }

        try {
            this.config = JSON.parse(this.configNode.textContent || '{}');
            this.app = new Application();
            await this.app.init({
                resizeTo: this.viewport,
                antialias: true,
                autoDensity: true,
                resolution: Math.min(window.devicePixelRatio || 1, 2),
                backgroundAlpha: 0,
                preference: 'webgl',
            });

            this.app.canvas.classList.add('battle-visual-stage__canvas');
            this.viewport.appendChild(this.app.canvas);

            const backgroundTexture = await Assets.load(this.config.background_url);
            this.background = new Sprite(backgroundTexture);
            this.app.stage.addChild(this.background);

            const atmosphere = new Graphics()
                .rect(0, 0, 100, 100)
                .fill({ color: 0x102019, alpha: 0.16 });
            atmosphere.label = 'atmosphere';
            this.app.stage.addChild(atmosphere);

            this.effects = new Container();
            this.app.stage.addChild(this.effects);

            await Promise.all(
                (this.config.participants || []).slice(0, 2).map((participant, index) => (
                    this.createFighter(participant, index)
                )),
            );

            this.layout();
            this.resizeObserver = new ResizeObserver(() => this.layout());
            this.resizeObserver.observe(this.viewport);
            this.bindControls();

            this.fallback?.classList.add('is-hidden');
            this.element.classList.add('is-pixi-ready');
        } catch (error) {
            console.error('Battle visualizer failed to initialize.', error);
            this.element.classList.add('is-static');
        }
    }

    async createFighter(participant, index) {
        const texture = await Assets.load(participant.image_url);
        const root = new Container();
        const shadow = new Graphics()
            .ellipse(0, 0, 90, 18)
            .fill({ color: 0x050807, alpha: 0.46 });
        const sprite = new Sprite(texture);
        const name = new Text({
            text: participant.creature_name || '?',
            style: {
                fill: 0xfff0c7,
                fontFamily: 'Arial, sans-serif',
                fontSize: 18,
                fontWeight: '700',
                stroke: { color: 0x18251e, width: 4 },
            },
        });
        const hpTrack = new Graphics();
        const hpFill = new Graphics();

        sprite.anchor.set(0.5, 1);
        name.anchor.set(0.5, 1);
        root.addChild(shadow, sprite, hpTrack, hpFill, name);
        this.app.stage.addChild(root);

        const fighter = {
            ...participant,
            index,
            root,
            sprite,
            shadow,
            name,
            hpTrack,
            hpFill,
            baseX: 0,
            baseY: 0,
            currentHp: Number(participant.hp_after ?? participant.hp_before ?? 1),
            maxHp: Math.max(1, Number(participant.hp_before || 1)),
        };

        this.fighters.set(Number(participant.creature_id), fighter);
        this.drawHealth(fighter);
    }

    layout() {
        if (!this.app || !this.background) {
            return;
        }

        const { width, height } = this.app.screen;
        const backgroundScale = Math.max(
            width / this.background.texture.width,
            height / this.background.texture.height,
        );

        this.background.scale.set(backgroundScale);
        this.background.position.set(
            (width - this.background.texture.width * backgroundScale) / 2,
            (height - this.background.texture.height * backgroundScale) / 2,
        );

        const atmosphere = this.app.stage.getChildByLabel('atmosphere');
        atmosphere?.clear().rect(0, 0, width, height).fill({ color: 0x102019, alpha: 0.16 });

        [...this.fighters.values()].forEach((fighter) => {
            const targetHeight = Math.min(height * 0.61, width * 0.31);
            const scale = targetHeight / Math.max(1, fighter.sprite.texture.height);
            const direction = fighter.index === 0 ? 1 : -1;
            const compact = width < 600;

            fighter.baseX = width * (fighter.index === 0 ? 0.25 : 0.75);
            fighter.baseY = height * 0.9;
            fighter.root.position.set(fighter.baseX, fighter.baseY);
            fighter.sprite.scale.set(scale * direction, scale);
            fighter.shadow.scale.set(Math.max(0.75, targetHeight / 380));
            fighter.name.style.fontSize = compact ? 12 : 18;
            fighter.name.style.wordWrap = compact;
            fighter.name.style.breakWords = compact;
            fighter.name.style.wordWrapWidth = compact ? width * 0.38 : width * 0.28;
            fighter.name.style.align = 'center';
            fighter.name.position.set(0, -targetHeight - (compact ? 5 : 12));
            fighter.hpTrack.position.set(-72, -targetHeight + 4);
            fighter.hpFill.position.copyFrom(fighter.hpTrack.position);
        });
    }

    drawHealth(fighter) {
        const ratio = Math.max(0, Math.min(1, fighter.currentHp / fighter.maxHp));
        const color = ratio > 0.55 ? 0x73c468 : (ratio > 0.25 ? 0xe0ad4f : 0xc95449);

        fighter.hpTrack
            .clear()
            .roundRect(0, 0, 144, 10, 3)
            .fill({ color: 0x111a16, alpha: 0.9 })
            .stroke({ color: 0xe8d59a, alpha: 0.6, width: 1 });
        fighter.hpFill
            .clear()
            .roundRect(1, 1, Math.max(0, 142 * ratio), 8, 2)
            .fill({ color });
    }

    bindControls() {
        this.pauseButton?.addEventListener('click', () => {
            this.paused = !this.paused;
            this.pauseButton.textContent = this.paused ? '▶' : 'Ⅱ';
            this.pauseButton.setAttribute('aria-pressed', this.paused ? 'true' : 'false');

            if (!this.paused) {
                this.processQueue();
            }
        });

        this.skipButton?.addEventListener('click', () => {
            this.skipRequested = true;
        });
    }

    applyState(state) {
        if (this.roundNode) {
            this.roundNode.textContent = String(Math.max(1, Number(state.current_round || 1)));
        }

        (state.participants || []).forEach((participant) => {
            const fighter = this.fighters.get(Number(participant.creature_id));

            if (!fighter) {
                return;
            }

            fighter.currentHp = Number(participant.hp_after ?? fighter.currentHp);
            fighter.maxHp = Math.max(1, Number(participant.hp_before ?? fighter.maxHp));
            fighter.result = participant.result;
            this.drawHealth(fighter);
        });

        this.enqueue(state.events || []);
    }

    enqueue(events) {
        events
            .filter((event) => Number(event.id) > this.latestEventId)
            .sort((left, right) => Number(left.id) - Number(right.id))
            .forEach((event) => {
                this.latestEventId = Math.max(this.latestEventId, Number(event.id));
                this.queue.push(event);
            });

        this.element.dataset.battleLatestEventId = String(this.latestEventId);
        this.processQueue();
    }

    async processQueue() {
        if (this.processing || this.paused || this.destroyed) {
            return;
        }

        this.processing = true;

        while (this.queue.length > 0 && !this.paused && !this.destroyed) {
            const event = this.queue.shift();
            await this.play(event);
        }

        this.processing = false;
    }

    async play(event) {
        const actor = this.fighters.get(Number(event.actor_creature_id));
        const target = this.fighters.get(Number(event.target_creature_id));
        const type = event.event_type;

        this.announce(event.text || '');

        if (HIT_EVENTS.has(type) && actor && target) {
            await this.attack(actor, target, event, CRITICAL_EVENTS.has(type));
        } else if (MISS_EVENTS.has(type) && actor && target) {
            await this.miss(actor, target);
        } else if (ITEM_EVENTS.has(type) && actor) {
            await this.itemEffect(actor, event);
        } else if (FINISH_EVENTS.has(type)) {
            await this.finish();
        } else {
            await this.delay(180);
        }
    }

    async attack(actor, target, event, critical) {
        const direction = target.baseX > actor.baseX ? 1 : -1;
        await this.tween(150, (progress) => {
            actor.root.x = actor.baseX + (direction * 45 * progress);
        });

        this.flash(target, critical ? 0xffc857 : 0xfff4cf, critical ? 0.85 : 0.56);
        this.floatText(
            target,
            `-${Number(event.payload?.damage || 0)}`,
            critical ? 0xffd451 : 0xfff0cf,
            critical ? 34 : 26,
        );

        await Promise.all([
            this.shake(target, critical ? 13 : 8, critical ? 330 : 230),
            this.tween(190, (progress) => {
                actor.root.x = actor.baseX + (direction * 45 * (1 - progress));
            }),
        ]);

        if (event.payload?.target_hp !== undefined) {
            target.currentHp = Math.max(0, Number(event.payload.target_hp));
            this.drawHealth(target);
        }

        await this.delay(critical ? 220 : 100);
    }

    async miss(actor, target) {
        const direction = target.baseX > actor.baseX ? 1 : -1;

        await Promise.all([
            this.tween(230, (progress) => {
                actor.root.x = actor.baseX + (direction * 36 * Math.sin(progress * Math.PI));
            }),
            this.tween(230, (progress) => {
                target.root.x = target.baseX + (direction * 24 * Math.sin(progress * Math.PI));
            }),
        ]);

        this.floatText(target, 'ПРОМАХ', 0xd8e3dc, 20);
        await this.delay(130);
    }

    async itemEffect(actor, event) {
        const heal = Number(event.payload?.heal || 0);
        const ring = new Graphics()
            .circle(0, 0, 26)
            .stroke({ color: heal > 0 ? 0x6fe08b : 0x65c6e8, width: 5, alpha: 0.9 });

        ring.position.set(actor.baseX, actor.baseY - 100);
        this.effects.addChild(ring);

        if (heal > 0) {
            this.floatText(actor, `+${heal}`, 0x7af094, 27);
        }

        await this.tween(520, (progress) => {
            ring.scale.set(1 + progress * 3);
            ring.alpha = 1 - progress;
        });
        ring.destroy();
    }

    async finish() {
        const loser = [...this.fighters.values()].find((fighter) => fighter.result === 'loss' || fighter.currentHp <= 0);
        const winner = [...this.fighters.values()].find((fighter) => fighter.result === 'win');

        if (loser) {
            await this.tween(450, (progress) => {
                loser.root.alpha = 1 - progress * 0.68;
                loser.root.rotation = (loser.index === 0 ? -1 : 1) * progress * 0.16;
                loser.root.y = loser.baseY + progress * 16;
            });
        }

        if (winner) {
            await this.tween(420, (progress) => {
                winner.root.y = winner.baseY - Math.sin(progress * Math.PI) * 18;
            });
        }
    }

    flash(fighter, color, alpha) {
        const flash = new Graphics()
            .circle(0, 0, 58)
            .fill({ color, alpha });
        flash.position.set(fighter.baseX, fighter.baseY - 120);
        this.effects.addChild(flash);

        this.tween(220, (progress) => {
            flash.scale.set(1 + progress * 2.2);
            flash.alpha = alpha * (1 - progress);
        }).then(() => flash.destroy());
    }

    floatText(fighter, text, color, fontSize) {
        const label = new Text({
            text,
            style: {
                fill: color,
                fontFamily: 'Arial, sans-serif',
                fontSize,
                fontWeight: '900',
                stroke: { color: 0x17221d, width: 5 },
            },
        });

        label.anchor.set(0.5);
        label.position.set(fighter.baseX, fighter.baseY - 150);
        this.effects.addChild(label);

        this.tween(700, (progress) => {
            label.y = fighter.baseY - 150 - progress * 72;
            label.alpha = 1 - Math.max(0, (progress - 0.62) / 0.38);
        }).then(() => label.destroy());
    }

    async shake(fighter, amount, duration) {
        await this.tween(duration, (progress) => {
            const strength = amount * (1 - progress);
            fighter.root.x = fighter.baseX + Math.sin(progress * Math.PI * 10) * strength;
        });
        fighter.root.x = fighter.baseX;
    }

    announce(text) {
        if (!this.announcer || !text) {
            return;
        }

        this.announcer.textContent = text;
        this.announcer.classList.remove('is-visible');
        window.requestAnimationFrame(() => this.announcer.classList.add('is-visible'));
    }

    async tween(duration, update) {
        if (this.skipRequested || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            update(1);
            this.skipRequested = false;
            return;
        }

        const startedAt = performance.now();

        await new Promise((resolve) => {
            const frame = (now) => {
                if (this.skipRequested || this.destroyed) {
                    update(1);
                    this.skipRequested = false;
                    resolve();
                    return;
                }

                const progress = Math.min(1, (now - startedAt) / duration);
                update(progress);

                if (progress >= 1) {
                    resolve();
                } else {
                    window.requestAnimationFrame(frame);
                }
            };

            window.requestAnimationFrame(frame);
        });
    }

    async delay(duration) {
        if (this.skipRequested) {
            this.skipRequested = false;
            return;
        }

        await wait(duration);
    }

    destroy() {
        this.destroyed = true;
        this.resizeObserver?.disconnect();
        this.app?.destroy(true, { children: true });
    }
}

export const setupBattleVisualizer = async (root = document) => {
    const element = root.querySelector('[data-battle-visualizer]');

    if (!element) {
        return null;
    }

    const visualizer = new BattleVisualizer(element);
    element.battleVisualizer = visualizer;
    await visualizer.init();

    return visualizer;
};
