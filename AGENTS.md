At the start of each session, load and follow the `using-agent-skills` skill before starting work.

## Frontend design

Use the `impeccable:impeccable` skill when designing, building, or improving frontend interfaces.

## Browser testing

Use Playwright for browser-based testing and verification.

## Object-oriented programming

Use object-oriented programming (OOP) when writing or modifying application code.

- Give each class one clear responsibility. Keep related state and behavior together, and put business rules in the class that owns the state or operation they govern.
- Encapsulate mutable state. Establish valid state during construction and preserve it through methods named for domain operations. Expose only the operations and data callers need, and protect internal collections from external mutation.
- Prefer composition over inheritance. Delegate work to focused collaborators. Use inheritance when the subtype can honor the base type's public contract wherever callers use it.
- Make dependencies explicit. Pass required collaborators to the constructor and operation-specific inputs to the method that uses them. Reuse existing contracts and introduce abstractions when they serve a current need.
- Keep stateless helpers as functions when a class would add no value. Use plain data for values that only carry information, and follow framework conventions at entry points while delegating domain behavior to the classes that own it.
- Apply these practices within the scope of the change. Reuse suitable classes and preserve existing caller behavior unless the task requires a change.
