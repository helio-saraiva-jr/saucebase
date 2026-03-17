<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    audience: 'representative' | 'client';
    quickLinks: Array<{
        label: string;
        url: string;
    }>;
    suggestions: string[];
}>();

const title = 'IA especialista';

const isClientAudience = computed(() => props.audience === 'client');
const homeHref = computed(() =>
    isClientAudience.value
        ? route('megacombo.client-portal')
        : route('dashboard'),
);

const question = ref('');

const placeholderAnswer = computed(() => {
    if (!question.value.trim()) {
        return 'Digite sua pergunta para receber orientação especializada sobre estratégia de consórcio, risco e valorização.';
    }

    return 'Recebi sua pergunta. Nesta etapa, esta tela funciona como ponto de entrada do atendimento com IA especialista. Na integração final, aqui aparecerá a resposta detalhada em tempo real.';
});
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            {
                title: 'Dashboard',
                href: homeHref,
            },
            { title: 'IA especialista' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div
                class="relative overflow-hidden rounded-2xl border bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-6 text-slate-50"
            >
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-36 w-36 rounded-full bg-indigo-400/20 blur-2xl"
                />
                <h1 class="text-3xl font-bold tracking-tight">
                    IA especialista
                </h1>
                <p class="mt-2 max-w-3xl text-slate-300">
                    Tire dúvidas sobre contemplação, ponto de virada, comparação
                    de cenários e estratégia de carteira de cotas com linguagem
                    simples e objetiva.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        v-for="quickLink in props.quickLinks"
                        :key="quickLink.url"
                        :href="quickLink.url"
                        class="rounded-full border border-slate-600 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800"
                    >
                        {{ quickLink.label }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Perguntas sugeridas</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <button
                            v-for="suggestion in props.suggestions"
                            :key="suggestion"
                            type="button"
                            class="hover:bg-muted w-full rounded-lg border p-3 text-left text-sm transition"
                            @click="question = suggestion"
                        >
                            {{ suggestion }}
                        </button>
                    </CardContent>
                </Card>

                <Card class="xl:col-span-3">
                    <CardHeader>
                        <CardTitle>Conversa com a IA</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            v-model="question"
                            rows="4"
                            class="focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2"
                            placeholder="Ex.: tenho 3 cotas e quero reduzir o tempo médio de contemplação sem aumentar muito o risco."
                        />

                        <div
                            class="mt-4 rounded-lg border bg-slate-50 p-4 dark:bg-slate-900/50"
                        >
                            <p class="text-muted-foreground text-xs">
                                Resposta da IA especialista
                            </p>
                            <p class="mt-2 text-sm leading-6">
                                {{ placeholderAnswer }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
