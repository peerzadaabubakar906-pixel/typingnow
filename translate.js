// Vercel serverless function: Roman Urdu -> English using the Gemini API.
// The API key is NOT in this file. Add it in Vercel: Settings > Environment Variables > GEMINI_API_KEY

module.exports = async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');

  const test = req.method === 'GET' && req.query && req.query.test;
  if (req.method !== 'POST' && !test) {
    return res.status(405).json({ error: 'Use POST' });
  }

  const key = process.env.GEMINI_API_KEY;
  const model = process.env.GEMINI_MODEL || 'gemini-3.8-flash';
  if (!key) {
    return res.status(500).json({ error: 'GEMINI_API_KEY is not set in Vercel Environment Variables (add it, then Redeploy)' });
  }

  let text = test ? 'kia hal hai' : String((req.body && req.body.text) || '').trim();
  if (!text) return res.status(200).json({ translation: '' });
  text = text.slice(0, 1500);

  const body = {
    systemInstruction: {
      parts: [{
        text:
          'You translate Roman Urdu (Urdu written in English letters) into natural, correct English. ' +
          'Reply with the English translation only: no notes, no quotes, no explanations. ' +
          'If the text is already English, return it unchanged.',
      }],
    },
    contents: [{ parts: [{ text }] }],
    generationConfig: { temperature: 0.2 },
  };

  try {
    const r = await fetch(
      `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-goog-api-key': key },
        body: JSON.stringify(body),
      }
    );
    const j = await r.json().catch(() => ({}));
    const parts = (j.candidates && j.candidates[0] && j.candidates[0].content && j.candidates[0].content.parts) || [];
    const out = parts.filter((p) => !p.thought && p.text).map((p) => p.text).join('').trim();

    if (!r.ok || !out) {
      const detail = (j.error && j.error.message) || `HTTP ${r.status}, empty reply`;
      return res.status(502).json({ error: test ? detail : 'translation_failed' });
    }
    return res.status(200).json({ translation: out });
  } catch (e) {
    return res.status(502).json({ error: test ? String(e) : 'translation_failed' });
  }
};
