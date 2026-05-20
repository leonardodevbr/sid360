# Mapa de lotes — como exportar polígonos

## Opção 1 — geojson.io (recomendado)

1. Abra [https://geojson.io](https://geojson.io)
2. Navegue até o loteamento (Flor de Girassol — aprox. `-11.677, -41.471`)
3. Desenhe cada lote com a ferramenta de polígono
4. Em cada feature, edite as **properties**:
   - `id`: ex. `res-01`
   - `name`: ex. `Lote Residencial 01`
   - `type`: `comercial` ou `residencial`
   - `popup`: texto HTML opcional do popup
5. Menu → **Save** → **GeoJSON**
6. Envie o arquivo `.geojson` — o site aceita o formato abaixo em `lotes-map.json`:

```json
{
  "geojson": { ...cole o FeatureCollection aqui... }
}
```

## Opção 2 — formato direto (sem GeoJSON)

Edite `public/data/lotes-map.json`:

```json
{
  "center": [-11.67715, -41.4716],
  "zoom": 17,
  "lots": [
    {
      "id": "res-01",
      "name": "Lote Residencial 01",
      "type": "residencial",
      "coords": [
        [-11.4674, -39.9840],
        [-11.4674, -39.9834],
        [-11.4669, -39.9834],
        [-11.4669, -39.9840]
      ],
      "popup": "<strong>Lote 01</strong><br>À vista: R$ 25.000"
    }
  ]
}
```

**Importante:** `coords` usa `[latitude, longitude]` (igual Leaflet), na ordem do perímetro do polígono.

## Cores automáticas

| `type`        | Cor      |
|---------------|----------|
| `comercial`   | Vermelho |
| `residencial` | Verde    |
