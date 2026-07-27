# Review Report Interpretation Framework

Use this framework to interpret LLM scoring results and present them to the SME.

## Reading the scores

Each dimension is scored 1-5. The overall score is the mean of all dimensions.

### Score scale

| Score | Meaning |
|-------|---------|
| 5 | Excellent — genuinely outstanding on this dimension |
| 4 | Good — minor improvements possible but solid |
| 3 | Adequate — functional but has clear room for improvement |
| 2 | Needs work — notable issues that should be addressed |
| 1 | Poor — fundamental problems on this dimension |

## Dimension-specific guidance

When a dimension scores low, use this guidance to advise the SME on what to fix.

### Clarity (SKILL.md and references)

Low clarity means the instructions are ambiguous or confusing. Common causes:

- Vague language where precise instructions are needed
- Missing prerequisite declarations (tools, runtimes, permissions)
- Instructions that could be interpreted multiple ways
- Poor formatting or disorganized structure

**Advice**: Rewrite ambiguous sections to have exactly one interpretation.
Declare all dependencies and prerequisites explicitly. Use consistent
formatting and logical section ordering.

### Actionability (SKILL.md only)

Low actionability means an agent cannot follow the instructions step-by-step.
Common causes:

- Abstract advice instead of concrete steps
- Missing intermediate steps that an agent would need
- Assumptions about context the agent won't have
- No examples of expected inputs/outputs

**Advice**: Convert abstract guidance into numbered steps. Add examples. Fill
in any gaps where an agent would need to guess what to do next.

### Token Efficiency (SKILL.md and references)

Low token efficiency means the content is bloated relative to its instructional
value. Common causes:

- Redundant explanations of the same concept
- Boilerplate text that doesn't help the agent
- Verbose phrasing where concise language would work
- Content that could be compressed without losing meaning

**Advice**: Cut redundant sections. Replace verbose explanations with concise
directives. Remove boilerplate. Every sentence should teach the agent something
it needs to know.

### Scope Discipline (SKILL.md only)

Low scope discipline means the skill sprawls beyond its stated purpose. Common
causes:

- Covering multiple languages or frameworks when the skill targets one
- Including tangential content that could confuse the agent
- Trying to do too many things in a single skill

**Advice**: Split broad skills into focused ones. Remove content that doesn't
directly serve the skill's stated purpose. If the skill mentions other
languages or frameworks, ensure those references are clearly delineated.

### Directive Precision (SKILL.md only)

Low directive precision means the skill hedges when it should be direct. Common
causes:

- Using "consider", "may", "could", "possibly" for required actions
- Missing conditional gates (when to proceed vs skip vs abort)
- Ambiguity about what is required vs optional

**Advice**: Replace hedged language with precise directives (must, always,
never, ensure). Add explicit conditions for branching paths. Make it clear
what is required vs optional.

### Novelty (SKILL.md and references)

Low novelty means the content mostly restates what the model already knows from
training data. This is the most important quality signal for deciding whether
a skill justifies its context window cost.

**Score below 3 — warning sign**: The skill may not contribute enough value.
The SME should critically evaluate whether the skill teaches the agent something
genuinely new. Common low-novelty patterns:

- Restating official documentation that models have already ingested
- Describing standard patterns or best practices that are common knowledge
- Covering well-documented public APIs without adding proprietary context
- Tutorials or guides on widely-known topics

**Advice**: Focus the skill on what is genuinely proprietary or non-obvious:
internal API conventions, organization-specific workflows, undocumented gotchas,
non-standard configurations, or domain knowledge not available in public docs.
Cut or heavily compress sections that just restate public knowledge.

For deeper context on why novelty matters and the research behind it, refer
the SME to: https://agentskillreport.com/

### Instructional Value (references only)

Low instructional value means the reference is abstract rather than practically
useful. Common causes:

- Descriptions without working code examples
- Theoretical explanations without concrete patterns
- API docs without usage examples or signatures

**Advice**: Add concrete, copy-pasteable code examples. Include actual API
signatures. Show patterns the agent can use directly, not just descriptions
of concepts.

### Skill Relevance (references only)

Low skill relevance means the reference includes content unrelated to the
parent skill's purpose. Common causes:

- Generic reference docs bundled without curation
- Tangential content that doesn't support the skill's task
- Reference files that cover a broader scope than the skill needs

**Advice**: Curate reference files tightly to the skill's purpose. Remove
sections that an agent would never need for the skill's specific task. If a
reference covers too broad a scope, extract only the relevant portions.

## Structuring the review summary

Present results to the SME in this order:

1. **Structural validation**: Did the skill pass? List any errors (blocking) or
   warnings (non-blocking).

2. **SKILL.md scores**: Show the per-dimension breakdown with the overall score.
   Highlight any dimension at 2 or below.

3. **Reference file scores** (if applicable): Show the per-file breakdown so
   the SME can see exactly which reference files need attention. Flag any
   file with an overall score below 3 or any individual dimension at 2 or
   below.

4. **Novelty assessment**: Explicitly call out whether the mean novelty score
   meets the threshold of 3. If it doesn't, this is the most important finding
   in the review. List the `novel_info` details for each file — these tell the
   SME what the LLM identified as genuinely new. The SME should verify these
   claims are accurate, since the LLM may hallucinate or miss truly novel
   content.

5. **Prioritized action items**: List specific things the SME should fix, ordered
   by impact. Structural errors first, then low-novelty concerns, then other
   low-scoring dimensions.

6. **Publish recommendation**: One of:
   - **Ready to publish** — passes structural validation, no dimension below 3,
     novelty >= 3
   - **Minor revisions needed** — passes structural validation, 1-2 dimensions
     need attention, novelty is borderline
   - **Significant rework needed** — structural errors, multiple low dimensions,
     or novelty below 3 with no clear path to improvement
