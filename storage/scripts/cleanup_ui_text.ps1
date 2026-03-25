$ErrorActionPreference = 'Stop'

$targets = Get-ChildItem -Path 'public_html/pages', 'public_html/assets/js' -Recurse -Include '*.php', '*.js'

$replacements = [ordered]@{
    "⚠️ ALERTA CANCELADO" = "ALERTA CANCELADO"
    "✏️ Editar Alerta" = "Editar alerta"
    "📄 Baixar PDF do Alerta" = "Baixar PDF do alerta"
    "🚫 Alerta cancelado" = "Alerta cancelado"
    "⏳ Gerando imagem do mapa…" = "Gerando imagem do mapa..."
    "🗺️ Gerando imagem do mapa para o PDF…" = "Gerando imagem do mapa para o PDF..."
    "⚠ Este alerta está ativo." = "Este alerta esta ativo."
    "✔ A <strong>data do alerta</strong>, a <strong>vigência</strong> e a" = "A <strong>data do alerta</strong>, a <strong>vigencia</strong> e a"
    "⏳ Importando alerta oficial do INMET..." = "Importando alerta oficial do INMET..."
    "⚠️ Este alerta do INMET já foi importado anteriormente." = "Este alerta do INMET ja foi importado anteriormente."
    "➕ Importar Alerta do INMET" = "Importar alerta do INMET"
    "✍️ Cadastrar Alerta" = "Cadastrar alerta"
    "➕ Importar Alerta" = "Importar alerta"
    "🧹 Limpar filtros" = "Limpar filtros"
    "⚠️ O mapa do alerta ainda não foi gerado." = "O mapa do alerta ainda nao foi gerado."
    "🗺️ Mapa não gerado" = "Mapa nao gerado"
    "✅ Alerta enviado em" = "Alerta enviado em"
    "✅ Enviado" = "Enviado"
    "✏️ Editar" = "Editar"
    "❌ Cancelar" = "Cancelar"
    "📄 PDF" = "PDF"
    "⏳ PDF" = "Gerando PDF"
    "🗺️ KML" = "KML"
    "ℹ️ Motivo" = "Motivo"
    "📌 Informações Oficiais" = "Informacoes oficiais"
    "⚠️ Riscos" = "Riscos"
    "🛡️ Recomendações" = "Recomendacoes"
    "🏙️ Municípios Afetados" = "Municipios afetados"
    "🧭 Regiões de Integração" = "Regioes de integracao"
    "🗺️ Área do Alerta" = "Area do alerta"
    "✔ Confirmar Importação" = "Confirmar importacao"
    "ℹ️ Entenda os índices" = "Entenda os indices"
    "🧹 Limpar" = "Limpar"
    "🔄 Atualizar" = "Atualizar"
    "❓ Ajuda" = "Ajuda"
    "🧭 Território do Alerta" = "Territorio do alerta"
    "⏳ Enviando..." = "Enviando..."
    "💬" = "IA"
    "🏛️ Defesa Civil Municipal" = "Defesa Civil Municipal"
    "💾 Salvar Alterações" = "Salvar alteracoes"
    "💾 Cadastrar Usuário" = "Cadastrar usuario"
    "← Voltar" = "Voltar"
    "← Cancelar" = "Cancelar"
    ">✖<" = ">X<"
    ">ℹ️<" = ">Info<"
    ">➤<" = ">Enviar<"
}

foreach ($file in $targets) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
    $updated = $content

    foreach ($entry in $replacements.GetEnumerator()) {
        $updated = $updated.Replace($entry.Key, $entry.Value)
    }

    $updated = $updated.Replace(
        "<span class='vigencia-expirada'>⚠ `$inicio → `$fim</span>",
        "<span class='vigencia-expirada'>`$inicio → `$fim</span>"
    )

    if ($updated -match "alert\('⚠️ É obrigatório desenhar a área afetada no mapa\.'\);") {
        $updated = $updated.Replace(
            "alert('⚠️ É obrigatório desenhar a área afetada no mapa.');",
            "alert('E obrigatorio desenhar a area afetada no mapa.');"
        )
    }

    if ($updated -ne $content) {
        Set-Content -Path $file.FullName -Value $updated -Encoding UTF8
    }
}
