<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            drawing: false,
            ctx: null,
            init() {
                const c = this.$refs.canvas;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                c.width = c.offsetWidth * ratio;
                c.height = c.offsetHeight * ratio;
                this.ctx = c.getContext('2d');
                this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.ctx.strokeStyle = '#111';
                this.redraw();
                this.$watch('state', (v) => { if (!v) this.ctx.clearRect(0, 0, c.width, c.height); });
            },
            redraw() {
                if (!this.state) return;
                const img = new Image();
                img.onload = () => this.ctx.drawImage(img, 0, 0, this.$refs.canvas.offsetWidth, this.$refs.canvas.offsetHeight);
                img.src = this.state;
            },
            pos(e) {
                const r = this.$refs.canvas.getBoundingClientRect();
                return { x: e.clientX - r.left, y: e.clientY - r.top };
            },
            start(e) { this.drawing = true; const p = this.pos(e); this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y); },
            move(e) { if (!this.drawing) return; const p = this.pos(e); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); },
            end() { if (!this.drawing) return; this.drawing = false; this.state = this.$refs.canvas.toDataURL('image/png'); },
            clear() { this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height); this.state = null; },
        }"
        wire:ignore
        class="space-y-2"
    >
        <canvas
            x-ref="canvas"
            @pointerdown.prevent="start($event)"
            @pointermove.prevent="move($event)"
            @pointerup.prevent="end()"
            @pointerleave="end()"
            style="width:100%;height:140px;touch-action:none;border:1px dashed #9ca3af;border-radius:8px;background:#fff;cursor:crosshair"
        ></canvas>
        <button type="button" x-on:click="clear()" class="text-sm text-gray-500 underline">Clear signature</button>
    </div>
</x-dynamic-component>
