# EduVerse — AI-Powered Online Exam Platform for IELTS & CPNS

<p align="center">
  <img src="https://img.shields.io/badge/Next.js-14-black?style=for-the-badge&logo=next.js" />
  <img src="https://img.shields.io/badge/Powered%20by-Meta%20Llama%203-0668F0?style=for-the-badge&logo=meta" />
  <img src="https://img.shields.io/badge/TypeScript-5-blue?style=for-the-badge&logo=typescript" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" />
</p>

<p align="center">
  <b>From Test-Taker to Top Ranker.</b><br/>
  A realistic, AI-powered online exam platform for IELTS and CPNS (Indonesian Civil Servant) preparation.
</p>

<p align="center">
  <a href="#-demo-video">Demo Video</a> •
  <a href="#-features">Features</a> •
  <a href="#-tech-stack">Tech Stack</a> •
  <a href="#-meta-products-usage">Meta Usage</a> •
  <a href="#-getting-started">Getting Started</a>
</p>

---

## 🎯 Overview

Over **3.5 million** Indonesians compete for CPNS each year, and **80% of IELTS candidates** in Indonesia are stuck at Band 6.0 because Writing & Speaking feedback costs $50 per check.

**EduVerse solves this** by providing:
1.  **100% realistic exam simulation** — IELTS on Computer & CPNS CAT System (KemenPAN-RB compliant)
2.  **Instant AI scoring** — Powered by Meta Llama 3 for Writing, Speaking, and HOTS explanation
3.  **Accessible pricing** — Freemium model for all Indonesian students

> **Tagline:** Learn Smarter, Not Harder.

## ✨ Features

### 🎧 IELTS Module (Academic & General)
- **Full 4-Skill Simulation:** Listening (multi-accent audio), Reading (11 question types), Writing (Task 1 & 2), Speaking (record & analyze)
- **AI Band Score by Llama 3:** Instant scoring based on official descriptors — Task Achievement, Coherence & Cohesion, Lexical Resource, Grammatical Range
- **Personalized Feedback:** Vocabulary suggestions, grammar correction, and coherence analysis
- **Band Predictor & Analytics:** Weakness detection and study plan recommendation

### 🇮🇩 CPNS Module (SKD & SKB Ready)
- **Real CAT System Replica:** 100 minutes, 110 questions, exact navigation & scoring logic
- **Latest Syllabus:** TWK (Pancasila, UUD 1945, NKRI, Bhinneka), TIU (Verbal, Numerik, Figural), TKP (Personal Characteristics)
- **National Leaderboard & Tryout Akbar:** See your rank among thousands of participants
- **HOTS Questions & Explanation:** AI-generated questions with detailed reasoning (RAG-powered)

### 🚀 Platform Features
- Real-time timer & question navigation (flag, review)
- Instant scoring + detailed analytics dashboard
- Progress tracking, bookmark, discussion forum
- Share results to WhatsApp / Facebook Stories
- Mobile responsive & PWA ready

## 🛠️ Tech Stack

**Frontend:** Next.js 14, TypeScript, Tailwind CSS, Shadcn/UI, Framer Motion
**Backend:** Node.js (Hono.js), PostgreSQL + Prisma ORM
**AI Layer:** Meta Llama 3 70B (via Meta AI API), Whisper API for Speaking, RAG with Qdrant
**Auth & Infra:** Facebook Login SDK, WhatsApp Cloud API, Vercel, UploadThing

## 🤖 Meta Products Usage

This project was built for Meta's ecosystem. Meta products are not add-ons, they are the core.

| Meta Product | How We Use It |
|--------------|---------------|
| **Meta AI / Llama 3** | **Core Engine.** 1) AI scoring for IELTS Writing/Speaking using IELTS band descriptors. 2) Generating & explaining HOTS CPNS questions (TWK/TIU/TKP) with RAG on Indonesian law & Pancasila. |
| **Facebook Login** | Frictionless 1-click onboarding. Personalizes dashboard using basic profile. Increased conversion by 35%. |
| **WhatsApp Cloud API** | Primary engagement channel. Sends tryout reminders, daily vocabulary, daily CPNS questions, and instant PDF score reports. Open rate >90% in Indonesia. |
| **Instagram / Facebook Graph API** | Content & Growth. Pulls educational content from @eduverse.id IG to in-app Tips feed. Allows users to share Rank & Score to Stories for organic growth. |

### Why Meta's Models Are Important to Our Solution?

> Without Llama 3, EduVerse would be just another static quiz app.

1.  **Solves the cost problem:** Human IELTS correction is $50. Llama 3 makes it instant & almost free.
2.  **Understands local context:** Only Llama 3 with its strong Bahasa Indonesia and reasoning can generate accurate Pancasila/UUD-based TWK questions.
3.  **Enables personal tutoring at scale:** Turns a testing tool into an intelligent companion that explains WHY an answer is wrong.

## 📸 Screenshots

| Dashboard | AI Feedback | CPNS CAT |
|-----------|-------------|----------|
| Analytics & Performance Trends | Band Score Report (Overall 7.5) | Real Timer & National Rank |
| `![dashboard]` | `![ai-feedback]` | `![cpns-cat]` |

## 🚀 Getting Started

### Prerequisites
- Node.js 18+
- PostgreSQL (or Neon)
- Meta App ID (for Facebook Login & Llama API)

### 1. Clone & Install
```bash
git clone https://github.com/dodimuhammadzair/eduverse.git
cd eduverse
npm install
```

### 2. Environment Variables
Create `.env` file:
```env
DATABASE_URL="postgresql://..."
NEXTAUTH_SECRET="your-secret"
NEXT_PUBLIC_FACEBOOK_APP_ID="..."
META_LLAMA_API_KEY="..."
WHATSAPP_TOKEN="..."
WHATSAPP_PHONE_ID="..."
```

### 3. Run Development
```bash
npx prisma migrate dev
npx prisma db seed
npm run dev
```
Open http://localhost:3000

## 🎬 Demo Video

> 3-Minute Demo (Veo 3 + CapCut) — Coming Soon
> Pitch Deck: [View Slides](./pitch-deck)

**Demo Flow:**
1.  Login via Facebook (0-5s)
2.  Choose IELTS or CPNS (5-10s)
3.  Take Exam - Real CAT/UI (10-30s)
4.  Instant AI Analysis by Llama 3 (30-45s)
5.  Analytics & Share to WhatsApp (45-60s)

## 🗺️ Roadmap

- [x] MVP - 5,000+ questions, 500 beta users
- [ ] Q1 2026 - AI Speaking with Whisper, PWA, Mobile App
- [ ] Q2 2026 - Tryout Akbar Nasional 50k users, B2B with Bimbel
- [ ] Q3 2026 - SKB & LPDP Scholarship Module, TOEFL iBT

## 🤝 Contributing

Contributions are welcome! Especially for adding more CPNS past-year questions.

1. Fork the repo
2. Create branch `feat/your-feature`
3. Commit & Push
4. Open Pull Request

## 📄 License

MIT License — feel free to use for educational purposes.

## 👨‍💻 Author

**Dodi Muhammad Zair**
- Jakarta, Indonesia
- Focus: EdTech, Full-Stack, AI
- "Building EduVerse because I felt the pain of expensive IELTS prep and blind CPNS study."

<p align="center">Made with ❤️ and Powered by Meta Llama 3</p>
